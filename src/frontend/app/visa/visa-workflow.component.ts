import { Component, Input, OnInit, ElementRef, ViewChild, Output, EventEmitter } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { CdkDragDrop, moveItemInArray } from '@angular/cdk/drag-drop';
import { FunctionsService } from '@service/functions.service';
import { tap, exhaustMap, map, startWith, catchError, finalize, filter } from 'rxjs/operators';
import { UntypedFormControl } from '@angular/forms';
import { LatinisePipe, ScanPipe } from 'ngx-pipes';
import { Observable, of } from 'rxjs';
import { MatDialog } from '@angular/material/dialog';
import { AddVisaModelModalComponent } from './addVisaModel/add-visa-model-modal.component';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { ActivatedRoute } from '@angular/router';
import { PrivilegeService } from '@service/privileges.service';
import { HeaderService } from '@service/header.service';

@Component({
    selector: 'app-visa-workflow',
    templateUrl: 'visa-workflow.component.html',
    styleUrls: ['visa-workflow.component.scss'],
    providers: [ScanPipe]
})
export class VisaWorkflowComponent implements OnInit {

    @Input() injectDatas: any;
    @Input() target: string = '';
    @Input() adminMode: boolean;
    @Input() resId: number = null;

    @Input() showListModels: boolean = true;
    @Input() showComment: boolean = true;

    @Output() workflowUpdated = new EventEmitter<any>();
    @ViewChild('searchVisaSignUserInput', { static: false }) searchVisaSignUserInput: ElementRef;

    visaWorkflow: any = {
        roles: ['sign', 'visa'],
        items: []
    };
    visaWorkflowClone: any = [];
    visaTemplates: any = {
        private: [],
        public: []
    };

    signVisaUsers: any = [];
    filteredSignVisaUsers: Observable<string[]>;
    filteredPublicModels: Observable<string[]>;
    filteredPrivateModels: Observable<string[]>;

    loading: boolean = false;
    hasHistory: boolean = false;
    visaModelListNotLoaded: boolean = true;
    data: any;

    searchVisaSignUser = new UntypedFormControl();

    loadedInConstructor: boolean = false;

    workflowSignatoryRole: string;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public functions: FunctionsService,
        private latinisePipe: LatinisePipe,
        public dialog: MatDialog,
        private scanPipe: ScanPipe,
        private route: ActivatedRoute,
        private privilegeService: PrivilegeService,
        public headerService: HeaderService
    ) {
        // ngOnInit is not called if navigating in the same component : must be in constructor for this case
        this.route.params.subscribe(params => {
            this.loading = true;

            this.resId = params['resId'];

            if (!this.functions.empty(this.resId)) {
                this.loadedInConstructor = true;
                this.loadWorkflow(this.resId);
            } else {
                this.loadedInConstructor = false;
            }

        }, (err: any) => {
            this.notify.handleErrors(err);
        });
    }

    ngOnInit(): void {
        this.checkWorkflowSignatoryRole();
        if (!this.functions.empty(this.resId) && !this.loadedInConstructor) {
            // this.initFilterVisaModelList();
            this.loadWorkflow(this.resId);
        } else {
            this.loading = false;
        }
    }

    drop(event: CdkDragDrop<string[]>) {
        if (event.previousContainer === event.container) {
            if (this.canManageUser(this.visaWorkflow.items[event.currentIndex], event.currentIndex)) {
                moveItemInArray(event.container.data, event.previousIndex, event.currentIndex);
                this.workflowUpdated.emit(event.container.data);
            } else {
                this.notify.error(this.translate.instant('lang.moveVisaUserErr', { value1: this.visaWorkflow.items[event.previousIndex].labelToDisplay }));
            }
        }
    }

    loadListModel(entityId: number) {
        this.loading = true;

        this.visaWorkflow.items = [];

        const route = `../rest/listTemplates/entities/${entityId}?type=visaCircuit`;

        return new Promise((resolve) => {
            this.http.get(route)
                .subscribe((data: any) => {
                    if (data.listTemplates[0]) {
                        this.visaWorkflow.items = data.listTemplates[0].items.map((item: any) => ({
                            ...item,
                            item_entity: item.descriptionToDisplay,
                            requested_signature: item.item_mode !== 'visa',
                            currentRole: item.item_mode
                        }));
                    }
                    this.visaWorkflowClone = JSON.parse(JSON.stringify(this.visaWorkflow.items));
                    this.loading = false;
                    resolve(true);
                });
        });
    }

    loadVisaSignUsersList() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/autocomplete/users/circuit').pipe(
                map((data: any) => {
                    data = data.map((user: any) => ({
                        id: user.id,
                        title: `${user.idToDisplay} (${user.otherInfo})`,
                        label: user.idToDisplay,
                        entity: user.otherInfo,
                        type: 'user',
                        hasPrivilege: true,
                        isValid: true,
                        currentRole: 'visa'
                    }));
                    return data;
                }),
                tap((data) => {
                    this.signVisaUsers = data;
                    this.filteredSignVisaUsers = this.searchVisaSignUser.valueChanges
                        .pipe(
                            startWith(''),
                            map(value => this._filter(value))
                        );
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    async loadVisaModelList() {
        if (!this.functions.empty(this.resId)) {
            await this.loadDefaultModel();
        }

        return new Promise((resolve, reject) => {
            this.http.get('../rest/availableCircuits?circuit=visa').pipe(
                tap((data: any) => {
                    this.visaTemplates.public = this.visaTemplates.public.concat(data.circuits.filter((item: any) => !item.private).map((item: any) => ({
                        id: item.id,
                        title: item.title,
                        label: item.title,
                        type: 'entity'
                    })));

                    this.visaTemplates.private = data.circuits.filter((item: any) => item.private).map((item: any) => ({
                        id: item.id,
                        title: item.title,
                        label: item.title,
                        type: 'entity'
                    }));
                    this.filteredPublicModels = this.searchVisaSignUser.valueChanges
                        .pipe(
                            startWith(''),
                            map(value => this._filterPublicModel(value))
                        );
                    this.filteredPrivateModels = this.searchVisaSignUser.valueChanges
                        .pipe(
                            startWith(''),
                            map(value => this._filterPrivateModel(value))
                        );
                    resolve(true);
                })
            ).subscribe();
        });
    }

    loadDefaultModel() {
        this.visaTemplates.public = [];

        return new Promise((resolve, reject) => {
            this.http.get(`../rest/resources/${this.resId}/defaultCircuit?circuit=visa`).pipe(
                filter((data: any) => !this.functions.empty(data.circuit)),
                tap((data: any) => {
                    if (!this.functions.empty(data.circuit)) {
                        this.visaTemplates.public.push({
                            id: data.circuit.id,
                            title: data.circuit.title,
                            label: data.circuit.title,
                            type: 'entity'
                        });
                    }
                }),
                finalize(() => resolve(true)),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    async initFilterVisaModelList() {
        if (this.visaModelListNotLoaded) {
            await this.loadVisaSignUsersList();

            if (this.showListModels) {
                await this.loadVisaModelList();
            }

            this.searchVisaSignUser.reset();

            this.visaModelListNotLoaded = false;
        }
    }

    loadWorkflow(resId: number) {
        this.resId = resId;
        this.loading = true;
        this.visaWorkflow.items = [];
        return new Promise((resolve) => {
            this.http.get('../rest/resources/' + resId + '/visaCircuit').pipe(
                tap((data: any) => {
                    if (!this.functions.empty(data.circuit)) {
                        data.circuit.forEach((element: any) => {
                            this.visaWorkflow.items.push(
                                {
                                    ...element,
                                    difflist_type: 'VISA_CIRCUIT',
                                    currentRole: this.getCurrentRole(element)
                                });
                        });
                        this.visaWorkflowClone = JSON.parse(JSON.stringify(this.visaWorkflow.items));
                    }
                    this.hasHistory = data.hasHistory;
                }),
                finalize(() => {
                    this.loading = false;
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    loadDefaultWorkflow(resId: number) {
        this.loading = true;
        this.visaWorkflow.items = [];
        return new Promise((resolve) => {
            this.http.get('../rest/resources/' + resId + '/defaultCircuit?circuit=visaCircuit').pipe(
                filter((data: any) => !this.functions.empty(data.circuit)),
                tap((data: any) => {
                    data.circuit.items.forEach((element: any) => {
                        this.visaWorkflow.items.push(
                            {
                                ...element,
                                requested_signature: element.item_mode !== 'visa',
                                difflist_type: 'VISA_CIRCUIT'
                            });
                    });
                    this.visaWorkflowClone = JSON.parse(JSON.stringify(this.visaWorkflow.items));
                    this.workflowUpdated.emit(this.visaWorkflow.items);
                }),
                finalize(() => {
                    this.loading = false;
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    deleteItem(index: number) {
        this.visaWorkflow.items.splice(index, 1);
        this.workflowUpdated.emit(this.visaWorkflow.items);
    }

    getVisaCount() {
        return this.visaWorkflow.items.length;
    }

    changeRole(i: number) {
        this.visaWorkflow.items[i].requested_signature = !this.visaWorkflow.items[i].requested_signature;
        this.visaWorkflow.items[i].currentRole = this.visaWorkflow.items[i].requested_signature ? 'sign' : 'visa';
        this.workflowUpdated.emit(this.visaWorkflow.items);
    }

    getWorkflow() {
        return this.visaWorkflow.items;
    }

    getCurrentVisaUserIndex() {
        if (this.functions.empty(this.getLastVisaUser()?.listinstance_id)) {
            const index = 0;
            return this.getRealIndex(index);
        } else {
            let index = this.visaWorkflow.items.map((item: any) => item.listinstance_id).indexOf(this.getLastVisaUser()?.listinstance_id);
            index++;
            return this.getRealIndex(index);
        }
    }

    getFirstVisaUser() {
        return !this.functions.empty(this.visaWorkflow.items[0]) && this.visaWorkflow.items[0].isValid ? this.visaWorkflow.items[0] : '';
    }

    /* getCurrentVisaUser() {

        const index = this.visaWorkflow.items.map((item: any) => item.listinstance_id).indexOf(this.getLastVisaUser().listinstance_id);

        return !this.functions.empty(this.visaWorkflow.items[index + 1]) ? this.visaWorkflow.items[index + 1] : '';
    }*/

    getNextVisaUser() {
        let index = this.getCurrentVisaUserIndex();
        index = index + 1;
        const realIndex = this.getRealIndex(index);

        return !this.functions.empty(this.visaWorkflow.items[realIndex]) ? this.visaWorkflow.items[realIndex] : '';
    }

    getLastVisaUser() {
        const arrOnlyProcess = this.visaWorkflow.items.filter((item: any) => !this.functions.empty(item.process_date) && item.isValid);

        return !this.functions.empty(arrOnlyProcess[arrOnlyProcess.length - 1]) ? arrOnlyProcess[arrOnlyProcess.length - 1] : null;
    }

    getRealIndex(index: number) {
        while (index < this.visaWorkflow.items.length && !this.visaWorkflow.items[index].isValid) {
            index++;
        }
        return index;
    }

    checkExternalSignatoryBook() {
        return this.visaWorkflow.items.filter((item: any) => this.functions.empty(item.externalId)).map((item: any) => item.labelToDisplay);
    }

    saveVisaWorkflow(resIds: number[] = [this.resId]) {
        return new Promise((resolve, reject) => {
            if (this.visaWorkflow.items.length === 0) {
                this.http.delete(`../rest/resources/${resIds[0]}/circuits/visaCircuit`).pipe(
                    tap(() => {
                        this.visaWorkflowClone = JSON.parse(JSON.stringify(this.visaWorkflow.items));
                        this.notify.success(this.translate.instant('lang.visaWorkflowDeleted'));
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        resolve(false);
                        return of(false);
                    })
                ).subscribe();
            } else if (this.isValidWorkflow()) {
                const arrVisa = resIds.map(resId => ({
                    resId: resId,
                    listInstances: this.visaWorkflow.items
                }));
                this.http.put('../rest/circuits/visaCircuit', { resources: arrVisa }).pipe(
                    tap((data: any) => {
                        this.visaWorkflowClone = JSON.parse(JSON.stringify(this.visaWorkflow.items));
                        this.notify.success(this.translate.instant('lang.visaWorkflowUpdated'));
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        resolve(false);
                        return of(false);
                    })
                ).subscribe();
            } else {
                this.notify.error(this.getError());
                resolve(false);
            }
        });
    }

    addItemToWorkflow(item: any) {
        return new Promise((resolve, reject) => {
            if (item.type === 'user') {
                const requestedSignature = !this.functions.empty(item.requested_signature) ? item.requested_signature : false;
                this.visaWorkflow.items.push({
                    item_id: item.id,
                    item_type: 'user',
                    item_entity: item.entity,
                    labelToDisplay: item.label,
                    externalId: !this.functions.empty(item.externalId) ? item.externalId : null,
                    difflist_type: 'VISA_CIRCUIT',
                    signatory: !this.functions.empty(item.signatory) ? item.signatory : false,
                    requested_signature: requestedSignature,
                    hasPrivilege: item.hasPrivilege,
                    isValid: item.isValid,
                    currentRole: requestedSignature ? 'sign' : 'visa'
                });
                this.searchVisaSignUser.reset();
                this.searchVisaSignUserInput.nativeElement.blur();
                this.workflowUpdated.emit(this.visaWorkflow.items);
                resolve(true);
            } else if (item.type === 'entity') {
                this.http.get(`../rest/listTemplates/${item.id}`).pipe(
                    tap((data: any) => {
                        this.visaWorkflow.items = this.visaWorkflow.items.concat(

                            data.listTemplate.items.map((itemTemplate: any) => ({
                                item_id: itemTemplate.item_id,
                                item_type: 'user',
                                labelToDisplay: itemTemplate.idToDisplay,
                                item_entity: itemTemplate.descriptionToDisplay,
                                difflist_type: 'VISA_CIRCUIT',
                                signatory: false,
                                requested_signature: itemTemplate.item_mode === 'sign',
                                hasPrivilege: itemTemplate.hasPrivilege,
                                isValid: itemTemplate.isValid,
                                currentRole: itemTemplate.item_mode
                            }))
                        );
                        this.searchVisaSignUser.reset();
                        this.searchVisaSignUserInput.nativeElement.blur();
                        this.workflowUpdated.emit(this.visaWorkflow.items);
                        resolve(true);
                    })
                ).subscribe();
            }
        });
    }

    resetWorkflow() {
        this.visaWorkflow.items = [];
    }

    isValidWorkflow() {
        if ((this.visaWorkflow.items.filter((item: any) => (!item.hasPrivilege || !item.isValid) && (item.process_date === null || this.functions.empty(item.process_date))).length === 0) && this.visaWorkflow.items.length > 0) {
            if (this.workflowSignatoryRole === 'optional') {
                return true;
            } else {
                return this.visaWorkflow.items.filter((item: any) => item.requested_signature).length > 0;
            }
        } else {
            return false;
        }
    }

    getError() {
        if (this.visaWorkflow.items.filter((item: any) => item.requested_signature).length === 0) {
            return this.translate.instant('lang.signUserRequired');
        } else if (this.visaWorkflow.items.filter((item: any) => !item.hasPrivilege).length > 0) {
            return this.translate.instant('lang.mustDeleteUsersWithNoPrivileges');
        } else if (this.visaWorkflow.items.filter((item: any) => !item.isValid && (item.process_date === null || this.functions.empty(item.process_date))).length > 0) {
            return this.translate.instant('lang.mustDeleteInvalidUsers');
        }
    }

    emptyWorkflow() {
        return this.visaWorkflow.items.length === 0;
    }

    workflowEnd() {
        if (this.visaWorkflow.items.filter((item: any) => !this.functions.empty(item.process_date)).length === this.visaWorkflow.items.length) {
            return true;
        } else {
            return false;
        }
    }

    openPromptSaveModel() {
        const dialogRef = this.dialog.open(AddVisaModelModalComponent, { panelClass: 'maarch-modal', data: { visaWorkflow: this.visaWorkflow.items } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => !this.functions.empty(data)),

            tap((data: any) => {
                this.visaTemplates.private.push({
                    id: data.id,
                    title: data.title,
                    label: data.title,
                    type: 'entity'
                });
                this.searchVisaSignUser.reset();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    deletePrivateModel(model: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete(`../rest/listTemplates/${model.id}`)),
            tap(() => {
                this.visaTemplates.private = this.visaTemplates.private.filter((template: any) => template.id !== model.id);
                this.searchVisaSignUser.reset();
                this.notify.success(this.translate.instant('lang.modelDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isModified() {
        return !(this.loading || JSON.stringify(this.visaWorkflow.items) === JSON.stringify(this.visaWorkflowClone));
    }

    canManageUser(item: any, i: number) {
        if (this.adminMode) {
            if (!this.functions.empty(item.process_date)) {
                return false;
            } else if (this.target === 'signatureBook' && this.getCurrentVisaUserIndex() === i) {
                return this.privilegeService.hasCurrentUserPrivilege('modify_visa_in_signatureBook');
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    getCurrentRole(item: any) {
        if (this.functions.empty(item.process_date)) {
            return item.requested_signature ? 'sign' : 'visa';
        } else {
            if (this.stringIncludes(item.process_comment, this.translate.instant('lang.visaWorkflowInterrupted'))) {
                return item.requested_signature ? 'sign' : 'visa';
            } else {
                return item.signatory ? 'sign' : 'visa';
            }
        }
    }

    stringIncludes(source, search) {
        if (source === undefined || source === null) {
            return false;
        }

        return source.includes(search);
    }

    checkWorkflowSignatoryRole() {
        this.http.get('../rest/parameters/workflowSignatoryRole').pipe(
            tap((data: any) => {
                if (!this.functions.empty(data.parameter)) {
                    this.workflowSignatoryRole = data.parameter.param_value_string;
                }
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    private _filter(value: string): string[] {
        if (typeof value === 'string') {
            const filterValue = this.latinisePipe.transform(value.toLowerCase());
            return this.signVisaUsers.filter((option: any) => this.latinisePipe.transform(option['title'].toLowerCase()).includes(filterValue));
        } else {
            return this.signVisaUsers;
        }
    }

    private _filterPrivateModel(value: string): string[] {
        if (typeof value === 'string') {
            const filterValue = this.latinisePipe.transform(value.toLowerCase());
            return this.visaTemplates.private.filter((option: any) => this.latinisePipe.transform(option['title'].toLowerCase()).includes(filterValue));
        } else {
            return this.visaTemplates.private;
        }
    }

    private _filterPublicModel(value: string): string[] {
        if (typeof value === 'string') {
            const filterValue = this.latinisePipe.transform(value.toLowerCase());
            return this.visaTemplates.public.filter((option: any) => this.latinisePipe.transform(option['title'].toLowerCase()).includes(filterValue));
        } else {
            return this.visaTemplates.public;
        }
    }
}
