import { Component, EventEmitter, Input, OnInit, Output, Renderer2 } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { CdkDragDrop, transferArrayItem } from '@angular/cdk/drag-drop';
import { UntypedFormControl } from '@angular/forms';
import { catchError, map, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { AlertComponent } from '../../plugins/modal/alert.component';
import { MatDialog } from '@angular/material/dialog';
import { FunctionsService } from '@service/functions.service';
import { HeaderService } from '@service/header.service';

@Component({
    selector: 'app-diffusions-list',
    templateUrl: 'diffusions-list.component.html',
    styleUrls: ['diffusions-list.component.scss'],
})
export class DiffusionsListComponent implements OnInit {

    /**
     * Ressource identifier to load listinstance (Incompatible with templateId)
     */
    @Input() resId: number = null;

    /**
      * Add previous dest in copy (Only compatible with resId)
      */
    @Input() keepDestForRedirection: boolean = false;

    /**
      * Entity identifier to load listModel of entity (Incompatible with resId)
      */
    @Input() entityId: any = null;

    /**
      * To specify the context to load listModel
      */
    @Input() selfDest: boolean = false;

    /**
      * To specify the context to load listModel
      */
    @Input() category: string = '';

    /**
      * For manage current loaded list
      */
    @Input() adminMode: boolean = false;

    /**
      * Ids of related allowed entities perimeters
      */
    @Input() allowedEntities: number[] = [];

    /**
      * Expand all roles
      */
    @Input() expanded: boolean = false;

    /**
      * Custom diffusion to display
      */
    @Input() customDiffusion: any[] = [];

    /**
      * To load privilege of current list management
      * @param indexation
      * @param details
      * @param process
      * @param redirect
      */
    @Input() target: string = '';

    /**
      * FormControl to use this component in form
      */
    @Input() diffFormControl: UntypedFormControl;

    /**
      * Catch external event after select an element in autocomplete
      */
    @Output() triggerEvent = new EventEmitter();

    roles: any = [];
    loading: boolean = true;
    hasHistory: boolean = false;
    availableRoles: any[] = [];
    keepRoles: any[] = [];
    currentEntityId: number = 0;
    userDestList: any[] = [];

    diffList: any = null;

    listinstanceClone: any = [];

    hasNoDest: boolean = false;
    keepDiffusionRoleInOutgoingIndexation: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private renderer: Renderer2,
        public dialog: MatDialog,
        public functions: FunctionsService,
        private headerService: HeaderService
    ) { }

    async ngOnInit(): Promise<void> {
        await this.initRoles();
        if (this.resId !== null && this.resId != 0 && this.target !== 'redirect') {
            this.loadListinstance(this.resId);
        } else if (((this.resId === null || this.resId == 0) && !this.functions.empty(this.entityId)) && this.customDiffusion.length === 0) {
            this.loadListModel(this.entityId, false, this.selfDest);
        } else if (this.customDiffusion.length > 0) {
            this.loadCustomDiffusion();
        }
        this.loading = false;
    }

    drop(event: CdkDragDrop<string[]>) {
        if (event.previousContainer === event.container) {
            // moveItemInArray(event.container.data, event.previousIndex, event.currentIndex);
        } else if (event.container.id != 'dest') {
            transferArrayItem(event.previousContainer.data,
                event.container.data,
                event.previousIndex,
                event.currentIndex);
        }
    }

    noReturnPredicate() {
        return false;
    }

    allPredicate() {
        return true;
    }

    loadCustomDiffusion() {
        const roles = [...new Set(this.customDiffusion.map(item => item.mode))];

        roles.forEach(role => {
            this.diffList[role].items = this.customDiffusion.filter((item: any) => item.mode === role).map((item: any) => ({
                item_mode: role,
                item_type: item.type,
                itemSerialId: item.id,
                itemId: '',
                itemLabel: item.labelToDisplay,
                itemSubLabel: item.descriptionToDisplay,
                difflist_type: 'entity_id',
                process_date: null,
                process_comment: null,
            }));
        });

        if (this.diffFormControl !== undefined) {
            this.setFormValues();
        }
    }

    async loadListModel(entityId: number, destResource: boolean = false, destCurrentUser: boolean = false) {
        this.loading = true;
        this.currentEntityId = entityId;
        this.userDestList = [];

        const listTemplates: any = await this.getListModel(entityId);
        this.removeAllItems();
        if (listTemplates.length > 0) {
            listTemplates[0].forEach((element: any) => {
                this.diffList[element.item_mode].items.push(element);
            });
        }

        // CASE IF CURRENT USER IS DEST (IN OUTGOING CATEGORY CASE)
        if (destCurrentUser && this.headerService.user.entities[0].id === entityId) {
            this.diffList['dest'].items = [
                {
                    item_mode: 'dest',
                    item_type: 'user',
                    itemSerialId: this.headerService.user.id,
                    itemId: '',
                    itemLabel: `${this.headerService.user.firstname} ${this.headerService.user.lastname}`,
                    itemSubLabel: this.headerService.user.entities[0].entity_label,
                    difflist_type: 'entity_id',
                    process_date: null,
                    process_comment: null,
                }
            ];
        }
        if (this.resId !== null) {
            const listInstance: any = await this.getListinstance(this.resId);

            if (listInstance !== undefined) {
                listInstance.forEach((element: any) => {
                    if (destResource && element.item_mode == 'dest') {
                        this.diffList[element.item_mode].items = [element];
                    }
                    if (this.keepRoles.indexOf(element.item_mode) > -1 && this.diffList[element.item_mode].items.filter((item: any) => item.itemSerialId === element.itemSerialId && item.item_type === element.item_type).length === 0) {
                        this.diffList[element.item_mode].items.push(element);
                    }
                    if (this.keepDestForRedirection && element.item_mode == 'dest' && this.diffList['cc'].items.filter((item: any) => item.itemSerialId === element.itemSerialId && item.item_type === element.item_type).length === 0) {
                        this.diffList['cc'].items.push(element);
                    }
                });
            }
        }

        if (this.category === 'outgoing' && !this.keepDiffusionRoleInOutgoingIndexation) {
            Object.keys(this.diffList).forEach(key => {
                if (key !== 'dest') {
                    this.diffList[key].items = [];
                }
            });
        }

        if (this.diffFormControl !== undefined) {
            this.setFormValues();
        }

        this.listinstanceClone = JSON.parse(JSON.stringify(this.getCurrentListinstance()));
        this.loading = false;
    }

    getListModel(entityId: number) {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/listTemplates/entities/${entityId}?type=diffusionList`).pipe(
                map((data: any) => {
                    data.listTemplates = data.listTemplates.map((item: any) => item.items.map((item: any) => {
                        const obj: any = {
                            listmodel_id: item.id,
                            listinstance_id: item.listinstance_id,
                            item_mode: item.item_mode,
                            item_type: item.item_type,
                            itemSerialId: item.item_id,
                            itemId: '',
                            itemLabel: item.labelToDisplay,
                            itemSubLabel: item.descriptionToDisplay,
                            difflist_type: 'entity_id',
                            process_date: null,
                            process_comment: null,
                        };
                        return obj;
                    }));
                    return data.listTemplates;
                }),
                tap((templates: any) => {
                    resolve(templates);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getListinstance(resId: number) {
        return new Promise((resolve, reject) => {
            if (resId != 0) {
                this.http.get(`../rest/resources/${resId}/listInstance`).pipe(
                    map((data: any) => {
                        this.hasHistory = data.hasHistory;
                        data.listInstance = data.listInstance.map((item: any) => {

                            const obj: any = {
                                listinstance_id: item.listinstance_id,
                                item_mode: item.item_mode,
                                item_type: item.item_type === 'user_id' ? 'user' : 'entity',
                                itemSerialId: item.itemSerialId,
                                itemId: item.item_id,
                                itemLabel: item.labelToDisplay,
                                itemSubLabel: item.descriptionToDisplay,
                                difflist_type: item.difflist_type,
                                process_date: null,
                                process_comment: null,
                            };
                            return obj;
                        });
                        return data.listInstance;
                    }),
                    tap((listInstance: any) => {
                        resolve(listInstance);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
            } else {
                resolve([]);
            }
        });
    }

    async loadListinstance(resId: number) {
        this.resId = resId;
        this.http.get(`../rest/resources/${resId}/fields/destination?alt=true`).pipe(
            tap((data: any) => {
                this.currentEntityId = data.field;
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();

        this.loading = true;

        const diffusions: any = await this.getListinstance(resId);
        this.removeAllItems();
        diffusions.forEach((element: any) => {
            if (!this.functions.empty(this.diffList[element.item_mode])) {
                this.diffList[element.item_mode].items.push(element);
            }
        });

        if (diffusions.filter((elem: any) => elem.item_mode === 'dest').length === 0 && !this.availableRoles.filter(role => role.id === 'dest')[0].canUpdate && this.adminMode) {
            this.adminMode = false;
            this.hasNoDest = true;
        }

        if (this.diffFormControl !== undefined) {
            this.setFormValues();
        }
        this.loading = false;
        this.listinstanceClone = JSON.parse(JSON.stringify(this.getCurrentListinstance()));
    }

    saveListinstance() {
        if (!this.hasEmptyDest()) {
            return new Promise((resolve, reject) => {
                const listInstance: any[] = [
                    {
                        resId: this.resId,
                        listInstances: this.getCurrentListinstance()
                    }
                ];
                this.http.put('../rest/listinstances', listInstance).pipe(
                    tap((data: any) => {
                        if (data && data.errors != null) {
                            this.notify.error(data.errors);
                        } else {
                            this.listinstanceClone = JSON.parse(JSON.stringify(this.getCurrentListinstance()));
                            this.notify.success(this.translate.instant('lang.diffusionListUpdated'));
                            resolve(true);
                        }
                    }),
                    catchError((err: any) => {
                        this.notify.handleErrors(err);
                        return of(false);
                    })
                ).subscribe();
            });
        } else {
            this.notify.error(this.translate.instant('lang.noDest'));
        }
    }

    initRoles() {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/roles?context=${this.target}`).pipe(
                map((data: any) => {
                    this.keepDiffusionRoleInOutgoingIndexation = data.parameters['keepDiffusionRoleInOutgoingIndexation'];
                    data.roles = data.roles.map((role: any) => ({
                        ...role,
                        id: role.id,
                    }));
                    return data.roles;
                }),
                tap((roles: any) => {
                    this.diffList = {};
                    this.availableRoles = roles;
                    this.availableRoles.forEach(element => {
                        this.diffList[element.id] = {
                            'label': element.label,
                            'items': []
                        };
                        if (element.keepInListInstance) {
                            this.keepRoles.push(element.id);
                        }
                    });
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    deleteItem(roleId: string, index: number) {
        this.diffList[roleId].items.splice(index, 1);
        if (this.diffFormControl !== undefined) {
            this.setFormValues();
        }
    }

    getCurrentListinstance() {
        const listInstanceFormatted: any = [];

        if (this.diffList !== null) {
            Object.keys(this.diffList).forEach(role => {
                if (this.diffList[role].items.length > 0) {
                    this.diffList[role].items.forEach((element: any) => {
                        listInstanceFormatted.push({
                            difflist_type: element.difflist_type,
                            item_id: element.itemSerialId,
                            item_mode: role === 'copy' ? 'cc' : role,
                            item_type: element.item_type,
                            process_date: element.process_date,
                            process_comment: element.process_comment,
                        });
                    });
                }
            });
        }


        return listInstanceFormatted;
    }

    loadDestUserList() {
        if (this.currentEntityId > 0 && this.userDestList.length == 0) {
            this.http.get('../rest/entities/' + this.currentEntityId + '/users')
                .subscribe((data: any) => {
                    this.userDestList = data.users;
                    this.loading = false;
                }, (err: any) => {
                    this.notify.handleErrors(err);
                });
        }
    }

    changeDest(user: any) {
        this.diffList['dest'].items[0] = {
            listinstance_id: null,
            item_mode: 'dest',
            item_type: 'user',
            itemSerialId: user.id,
            itemId: user.user_id,
            itemLabel: user.labelToDisplay,
            itemSubLabel: user.descriptionToDisplay,
            difflist_type: 'entity_id',
            process_date: null,
            process_comment: null,
        };
    }

    getDestUser() {
        if (this.diffList !== null && this.diffList['dest']) {
            return this.diffList['dest'].items;
        } else {
            return false;
        }
    }

    async addElem(element: any) {
        let item_mode: any = 'cc';

        if (this.hasEmptyDest() && element.type == 'user') {
            if (this.currentEntityId) {
                item_mode = await this.isUserInCurrentEntity(element.serialId) && this.availableRoles.filter(role => role.id === 'dest')[0].canUpdate ? 'dest' : 'cc';
            } else {
                item_mode = this.availableRoles.filter(role => role.id === 'dest')[0].canUpdate ? 'dest' : 'cc';
            }
        }

        let itemType = '';
        if (element.type == 'user') {
            itemType = 'user';
        } else {
            itemType = 'entity';
        }

        const newElemListModel: any = {
            listinstance_id: null,
            item_mode: item_mode,
            item_type: itemType,
            itemSerialId: element.serialId,
            itemId: element.id,
            itemLabel: element.idToDisplay,
            itemSubLabel: element.descriptionToDisplay,
            difflist_type: 'entity_id',
            process_date: null,
            process_comment: null,
        };

        if (!this.isItemInThisRole(newElemListModel, 'cc')) {
            this.diffList[item_mode].items.unshift(newElemListModel);

            if (this.diffFormControl !== undefined) {
                this.setFormValues();
            }
        }
    }

    isItemInThisRole(element: any, roleId: string) {
        const result = this.diffList[roleId].items.map((item: any, index: number) => ({
            ...item,
            index: index
        })).filter((item: any) => item.itemSerialId === element.itemSerialId && item.item_type === element.item_type);

        return result.length > 0;
    }

    isUserInCurrentEntity(userId: number) {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/entities/${this.currentEntityId}/users`).pipe(
                tap((data: any) => {
                    const state = data.users.filter((user: any) => user.id === userId).length > 0;
                    resolve(state);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    removeAllItems() {
        Object.keys(this.diffList).forEach((element: any) => {
            this.diffList[element].items = [];
        });
    }

    hasEmptyDest() {
        return !this.functions.empty(this.diffList) && this.diffList['dest'].items.length === 0;
    }

    isEmptyList() {
        let state = true;
        if (this.diffList !== null) {
            Object.keys(this.diffList).forEach((element: any) => {
                if (this.diffList[element].items.length > 0) {
                    state = false;
                }
            });
        }
        return state;
    }

    changeRole(user: any, oldRole: any, newRole: any) {
        if (newRole.id === 'dest') {
            this.switchUserWithOldDest(user, oldRole);
        } else {
            this.changeUserRole(user, oldRole, newRole);
        }
    }

    switchMode() {
        this.adminMode = !this.adminMode;

        if (this.adminMode && this.canUpdateRoles()) {
            setTimeout(() => {
                this.renderer.selectRootElement('#autoCompleteInput').focus();
            }, 100);

        }
    }

    switchUserWithOldDest(user: any, oldRole: any) {
        this.http.get('../rest/users/' + user.itemSerialId + '/entities').pipe(
            map((data: any) => {
                data.entities = data.entities.map((entity: any) => entity.id);
                return data;
            }),
            tap((data: any) => {
                let indexFound = -1;
                let isAllowed: boolean = false;
                const allowedEntitiesIds: number[] = [];

                data.entities.forEach(entityValue => {
                    if (this.allowedEntities.indexOf(entityValue) > -1) {
                        isAllowed = true;
                        allowedEntitiesIds.push(entityValue);
                    }
                });
                if (isAllowed || this.target === 'process' || this.target === 'details') {
                    if (this.diffList['dest'].items.length > 0) {
                        const destUser = this.diffList['dest'].items[0];

                        indexFound = this.diffList[oldRole.id].items.map((item: any) => item.itemSerialId).indexOf(destUser.itemSerialId);

                        if (indexFound === -1 && !this.isItemInThisRole(destUser, oldRole.id)) {
                            destUser.item_mode = oldRole.id;
                            this.diffList[oldRole.id].items.push(destUser);
                        }
                    }

                    const result = this.diffList[oldRole.id].items.map((item: any, index: number) => ({
                        ...item,
                        index: index
                    })).filter((item: any) => item.itemSerialId === user.itemSerialId && item.item_type === user.item_type);

                    if (result.length > 0) {
                        this.diffList[oldRole.id].items.splice(result[0].index, 1);
                    }

                    user.item_mode = 'dest';
                    this.diffList['dest'].items[0] = user;

                    if (this.diffFormControl !== undefined) {
                        this.setFormValues();
                    }
                    // TO CHANGE DESTINATION IN INDEXING FORM
                    if (this.triggerEvent !== undefined) {
                        this.triggerEvent.emit(allowedEntitiesIds);
                    }
                } else {
                    this.dialog.open(AlertComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.userUnauthorized'), msg: '<b>' + user.itemLabel + '</b> ' + this.translate.instant('lang.notInAuthorizedEntities') } });
                }
            }),
        ).subscribe();
    }

    changeUserRole(user: any, oldRole: any, newRole: any) {
        let indexFound: number;

        indexFound = this.diffList[oldRole.id].items.map((item: any) => item.itemSerialId).indexOf(user.itemSerialId);
        if (indexFound > -1) {
            this.diffList[oldRole.id].items.splice(indexFound, 1);
        }
        if (!this.isItemInThisRole(user, newRole.id)) {
            user.item_mode = newRole.id;
            this.diffList[newRole.id].items.push(user);
        }
        if (this.diffFormControl !== undefined) {
            this.setFormValues();
        }
    }

    setFormValues() {
        let arrValues: any[] = [];
        Object.keys(this.diffList).forEach(role => {
            arrValues = arrValues.concat(
                this.diffList[role].items.map((item: any) => ({
                    id: item.itemSerialId,
                    mode: role,
                    type: item.item_type === 'user' ? 'user' : 'entity'
                }))
            );
        });
        this.diffFormControl.setValue(arrValues);
        this.diffFormControl.markAsTouched();
    }

    canUpdateRoles() {
        return this.availableRoles.filter((role: any) => role.canUpdate === true).length > 0;
    }

    isModified() {
        return JSON.stringify(this.listinstanceClone) !== JSON.stringify(this.getCurrentListinstance());
    }
}
