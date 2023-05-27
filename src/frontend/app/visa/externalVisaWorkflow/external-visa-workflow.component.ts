import { Component, Input, OnInit, Output, EventEmitter } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { CdkDragDrop, moveItemInArray } from '@angular/cdk/drag-drop';
import { FunctionsService } from '@service/functions.service';
import { tap, catchError } from 'rxjs/operators';
import { UntypedFormControl } from '@angular/forms';
import { ScanPipe } from 'ngx-pipes';
import { Observable, of } from 'rxjs';
import { MatDialog } from '@angular/material/dialog';
import { CreateExternalUserComponent } from './createExternalUser/create-external-user.component';
import { ActionsService } from '@appRoot/actions/actions.service';
import { ExternalSignatoryBookManagerService } from '@service/externalSignatoryBook/external-signatory-book-manager.service';
import { UserWorkflow } from '@models/user-workflow.model';
import { AuthService } from '@service/auth.service';

@Component({
    selector: 'app-external-visa-workflow',
    templateUrl: 'external-visa-workflow.component.html',
    styleUrls: ['external-visa-workflow.component.scss'],
    providers: [ScanPipe, ExternalSignatoryBookManagerService]
})
export class ExternalVisaWorkflowComponent implements OnInit {

    @Input() injectDatas: any;
    @Input() adminMode: boolean;
    @Input() resId: number = null;

    @Output() workflowUpdated = new EventEmitter<any>();

    visaWorkflowClone: any = [];
    visaTemplates: any = {
        private: [],
        public: []
    };

    signVisaUsers: any = [];
    filteredPrivateModels: Observable<string[]>;

    loading: boolean = false;
    data: any;

    searchVisaSignUser = new UntypedFormControl();

    loadedInConstructor: boolean = false;

    otpConfig: number = 0;

    visaWorkflow = new VisaWorkflow();

    workflowTypes: any[] = [];
    workflowType: string = 'BUREAUTIQUE_PDF';

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public functions: FunctionsService,
        public dialog: MatDialog,
        public actionService: ActionsService,
        public authService: AuthService,
        public externalSignatoryBookManagerService: ExternalSignatoryBookManagerService,
        private notify: NotificationService
    ) { }

    async ngOnInit(): Promise<any> {
        this.workflowDetails();
        if (this.externalSignatoryBookManagerService.canAddExternalUser()) {
            const data: any = await this.externalSignatoryBookManagerService?.getOtpConfig();
            if (!this.functions.empty(data)) {
                this.otpConfig = data.length;
            }
        }
    }

    async getWorkflowDetails(): Promise<any> {
        return await this.externalSignatoryBookManagerService?.getWorkflowDetails();
    }

    drop(event: CdkDragDrop<string[]>) {
        if (event.previousContainer === event.container) {
            if (this.canMoveUserExtParaph(event)) {
                moveItemInArray(event.container.data, event.previousIndex, event.currentIndex);
            } else {
                this.notify.error(this.translate.instant('lang.errorUserSignType'));
            }
        }
    }

    canMoveUserExtParaph(ev: any) {
        const newWorkflow = this.arrayMove(this.visaWorkflow.items.slice(), ev.currentIndex, ev.previousIndex);
        const res = this.isValidExtWorkflow(newWorkflow);
        return res;
    }

    arrayMove(arr: any, oldIndex: number, newIndex: number) {
        if (newIndex >= arr.length) {
            let k = newIndex - arr.length + 1;
            while (k--) {
                arr.push(undefined);
            }
        }
        arr.splice(newIndex, 0, arr.splice(oldIndex, 1)[0]);
        return arr;
    }

    isValidExtWorkflow(workflow: any = this.visaWorkflow): boolean {
        return this.externalSignatoryBookManagerService.isValidExtWorkflow(workflow);
    }

    async workflowDetails() {
        const workflow = await this.getWorkflowDetails();
        if (!this.functions.empty(workflow?.types)) {
            this.workflowTypes = workflow.types;
            this.workflowType = workflow.types[0].id;
        }
    }

    async loadListModel(entityId: number) {
        this.loading = true;
        this.visaWorkflow.items = [];
        this.workflowDetails();
        const listModel: any = await this.externalSignatoryBookManagerService?.loadListModel(entityId);
        if (!this.functions.empty(listModel)) {
            if (listModel.listTemplates[0]) {
                this.visaWorkflow.items = listModel.listTemplates[0].items.map((item: any) => ({
                    ...this.externalSignatoryBookManagerService.setExternalInformation(item),
                    item_entity: item.descriptionToDisplay,
                    requested_signature: item.item_mode !== 'visa',
                    currentRole: item.item_mode
                }));
            }
            this.visaWorkflow.items.forEach((element: any, key: number) => {
                if (!this.functions.empty(element['externalId'])) {
                    this.getUserAvatar(element.externalId[this.authService.externalSignatoryBook.id], key);
                }
            });
            this.visaWorkflowClone = JSON.parse(JSON.stringify(this.visaWorkflow.items));
        }
        this.loading = false;
    }

    async loadExternalWorkflow(attachmentId: number, type: string) {
        this.loading = true;
        this.visaWorkflow.items = [];
        const data: any = await this.externalSignatoryBookManagerService?.loadWorkflow(attachmentId, type);
        if (!this.functions.empty(data.workflow)) {
            data.workflow.forEach((element: any, key: any) => {
                const user: UserWorkflow = {
                    listinstance_id: key,
                    id: element.userId,
                    labelToDisplay: element.userDisplay,
                    item_type: 'user',
                    requested_signature: element.mode !== 'visa',
                    process_date: this.functions.formatFrenchDateToTechnicalDate(element.processDate),
                    picture: '',
                    hasPrivilege: true,
                    isValid: true,
                    delegatedBy: null,
                    role: element.mode !== 'visa' ? element.signatureMode : 'visa',
                    status: element.status
                };
                let externalId: string | number = element.userId;
                if (this.functions.empty(element.userId) && !this.functions.empty(element.externalInformations)) {
                    user['role'] = element.mode;
                    externalId = element.externalInformations.type;
                }
                this.visaWorkflow.items.push(user);
                this.getUserAvatar(externalId, key);
            });
        }
        this.loading = false;
    }

    deleteItem(index: number) {
        this.visaWorkflow.items.splice(index, 1);
        this.workflowUpdated.emit(this.visaWorkflow.items);
    }

    getVisaCount(): number {
        return this.visaWorkflow.items.length;
    }

    getWorkflow(): UserWorkflow[] {
        return this.visaWorkflow.items;
    }

    getCurrentVisaUserIndex(): number {
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

    getNextVisaUser() {
        let index = this.getCurrentVisaUserIndex();
        index = index + 1;
        const realIndex = this.getRealIndex(index);

        return !this.functions.empty(this.visaWorkflow.items[realIndex]) ? this.visaWorkflow.items[realIndex] : '';
    }

    getLastVisaUser(): UserWorkflow | null {
        const arrOnlyProcess = this.visaWorkflow.items.filter((item: any) => !this.functions.empty(item.process_date) && item.isValid);
        return !this.functions.empty(arrOnlyProcess[arrOnlyProcess.length - 1]) ? arrOnlyProcess[arrOnlyProcess.length - 1] : null;
    }

    getRealIndex(index: number): number {
        while (index < this.visaWorkflow.items.length && !this.visaWorkflow.items[index].isValid) {
            index++;
        }
        return index;
    }

    getUserOtpsWorkflow(): string[] {
        return this.visaWorkflow.items.filter((item: any) => this.functions.empty(item.externalId) && item.hasOwnProperty('externalInformations') !== undefined).map((item: any) => item.labelToDisplay);
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
        item = this.externalSignatoryBookManagerService.setExternalInformation(item);
        return new Promise((resolve, reject) => {
            const user: UserWorkflow = {
                item_id: item.id,
                item_type: 'user',
                item_entity: item.email,
                labelToDisplay: item.idToDisplay,
                externalId: item.externalId,
                difflist_type: 'VISA_CIRCUIT',
                signatory: !this.functions.empty(item.signatory) ? item.signatory : false,
                hasPrivilege: true,
                isValid: true,
                availableRoles : [... new Set(['visa'].concat(item.signatureModes))],
                role: item.signatureModes[item.signatureModes.length - 1]
            };
            this.visaWorkflow.items.push(user);
            if (!this.isValidRole(this.visaWorkflow.items.length - 1, item.signatureModes[item.signatureModes.length - 1], item.signatureModes[item.signatureModes.length - 1])) {
                this.visaWorkflow.items[this.visaWorkflow.items.length - 1].role = 'visa';
            }
            this.getUserAvatar(item.externalId[this.externalSignatoryBookManagerService.signatoryBookEnabled], this.visaWorkflow.items.length - 1);
            this.searchVisaSignUser.reset();
            resolve(true);
        });
    }

    resetWorkflow() {
        this.visaWorkflow.items = [];
    }

    isValidWorkflow(): boolean {
        if ((this.visaWorkflow.items.filter((item: any) => item.requested_signature).length > 0 && this.visaWorkflow.items.filter((item: any) => (!item.hasPrivilege || !item.isValid) && (item.process_date === null || this.functions.empty(item.process_date))).length === 0) && this.visaWorkflow.items.length > 0) {
            return true;
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

    emptyWorkflow(): boolean {
        return this.visaWorkflow.items.length === 0;
    }

    workflowEnd(): boolean {
        if (this.visaWorkflow.items.filter((item: any) => !this.functions.empty(item.process_date)).length === this.visaWorkflow.items.length) {
            return true;
        } else {
            return false;
        }
    }

    async getUserAvatar(externalId: any = null, key: number) {
        this.visaWorkflow.items[key].picture = await this.externalSignatoryBookManagerService?.getUserAvatar(externalId);
    }

    isModified(): boolean {
        return !(this.loading || JSON.stringify(this.visaWorkflow.items) === JSON.stringify(this.visaWorkflowClone));
    }

    canManageUser(): boolean {
        return this.adminMode;
    }

    isValidRole(indexWorkflow: any, role: string, currentRole: string): boolean {
        if (this.visaWorkflow.items.filter((item: any, index: any) => index > indexWorkflow && ['stamp'].indexOf(item.role) > -1).length > 0 && ['visa', 'stamp'].indexOf(currentRole) > -1 && ['visa', 'stamp'].indexOf(role) === -1) {
            return false;
        } else if (this.visaWorkflow.items.filter((item: any, index: any) => index < indexWorkflow && ['visa', 'stamp'].indexOf(item.role) === -1).length > 0 && role === 'stamp') {
            return false;
        } else {
            return true;
        }
    }

    openCreateUserOtp(item: any = null) {
        if (this.adminMode && (item === null || (item && item.item_id === null))) {
            const objToSend: any = item === null || (item && item.item_id) !== null ? null : {
                firstname: item.externalInformations.firstname,
                lastname: item.externalInformations.lastname,
                email: item.externalInformations.email,
                phone: item.externalInformations.phone,
                security: item.externalInformations.security,
                sourceId: item.externalInformations.sourceId,
                type: item.externalInformations.type,
                role: item.role,
                availableRoles: item.externalInformations.availableRoles
            };
            const dialogRef = this.dialog.open(CreateExternalUserComponent, {
                panelClass: 'maarch-modal',
                disableClose: true,
                width: '500px',
                data: { otpInfo : objToSend, resId : this.resId}
            });
            dialogRef.afterClosed().pipe(
                tap(async (data: any) => {
                    if (data) {
                        const user: UserWorkflow = {
                            item_id: null,
                            item_type: 'userOtp',
                            labelToDisplay: `${data.otp.firstname} ${data.otp.lastname}`,
                            picture: await this.actionService.getUserOtpIcon(data.otp.type),
                            hasPrivilege: true,
                            isValid: true,
                            externalId: {
                                maarchParapheur: null
                            },
                            externalInformations: data.otp,
                            role: data.otp.role,
                            availableRoles: data.otp.availableRoles
                        };
                        if (objToSend !== null) {
                            this.visaWorkflow.items[this.visaWorkflow.items.indexOf(item)] = user;
                            this.notify.success(this.translate.instant('lang.modificationSaved'));
                        } else {
                            this.visaWorkflow.items.push(user);
                        }
                    }
                })
            ).subscribe();
        }
    }

    updateVisaWorkflow(user: any) {
        this.visaWorkflow.items.push(user);
    }

    setPositionsWorkfow(resource: any, positions: any) {
        this.clearOldPositionsFromResource(resource);

        if (positions.signaturePositions !== undefined) {
            Object.keys(positions.signaturePositions).forEach(key => {
                const objPos = {
                    ...positions.signaturePositions[key],
                    mainDocument : resource.mainDocument,
                    resId: resource.resId
                };
                this.visaWorkflow.items[positions.signaturePositions[key].sequence].signaturePositions.push(objPos);
            });
        }
        if (positions.datePositions !== undefined) {
            Object.keys(positions.datePositions).forEach(key => {
                const objPos = {
                    ...positions.datePositions[key],
                    mainDocument : resource.mainDocument,
                    resId: resource.resId
                };
                this.visaWorkflow.items[positions.datePositions[key].sequence].datePositions.push(objPos);
            });
        }
    }

    clearOldPositionsFromResource(resource: any) {
        this.visaWorkflow.items.forEach((user: any) => {

            if (user.signaturePositions === undefined) {
                user.signaturePositions = [];
            } else {
                const signaturePositionsToKeep = [];
                user.signaturePositions.forEach((pos: any) => {
                    if (pos.resId !== resource.resId && pos.mainDocument === resource.mainDocument) {
                        signaturePositionsToKeep.push(pos);
                    } else if (pos.mainDocument !== resource.mainDocument) {
                        signaturePositionsToKeep.push(pos);
                    }
                });
                user.signaturePositions = signaturePositionsToKeep;
            }

            if (user.datePositions === undefined) {
                user.datePositions = [];
            } else {
                const datePositionsToKeep = [];
                user.datePositions.forEach((pos: any) => {
                    if (pos.resId !== resource.resId && pos.mainDocument === resource.mainDocument) {
                        datePositionsToKeep.push(pos);
                    } else if (pos.mainDocument !== resource.mainDocument) {
                        datePositionsToKeep.push(pos);
                    }
                });
                user.datePositions = datePositionsToKeep;
            }
        });
    }

    getDocumentsFromPositions() {
        const documents: any[] = [];
        this.visaWorkflow.items.forEach((user: any) => {
            user.signaturePositions?.forEach(element => {
                documents.push({
                    resId: element.resId,
                    mainDocument: element.mainDocument
                });
            });
            user.datePositions?.forEach(element => {
                documents.push({
                    resId: element.resId,
                    mainDocument: element.mainDocument
                });
            });
        });
        return documents;
    }

    hasOtpNoSignaturePositionFromResource(resource: any): boolean {
        let state: boolean = true;
        this.visaWorkflow.items.filter((user: any) => user.item_id === null && user.role === 'sign').forEach((user: any) => {
            if (user.signaturePositions?.filter((pos: any) => pos.resId === resource.resId && pos.mainDocument === resource.mainDocument).length > 0) {
                state = false;
            }
        });
        return state;
    }

    getRouteDatas(): string[] {
        return [this.externalSignatoryBookManagerService.getAutocompleteUsersRoute()];
    }

    getWorkflowTypeLabel(workflowType: string) {
        return this.workflowTypes.find((item: any) => item.id === workflowType).label;
    }
}

export interface VisaWorkflow {
    type: string;
    roles: string[];
    items: UserWorkflow[];
}

export class VisaWorkflow implements VisaWorkflow {
    constructor() {
        this.type = null;
        this.roles = ['visa', 'sign'];
        this.items = [];
    }
}
