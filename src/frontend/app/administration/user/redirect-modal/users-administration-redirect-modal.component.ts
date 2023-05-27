import { HttpClient } from '@angular/common/http';
import { Component, Inject, OnInit } from '@angular/core';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { catchError, tap } from 'rxjs/operators';

@Component({
    templateUrl: 'users-administration-redirect-modal.component.html',
    styleUrls: ['users-administration-redirect-modal.scss'],
})
export class UsersAdministrationRedirectModalComponent implements OnInit {

    modalTitle: string = 'lang.confirmAction';

    isDeletable: boolean = false;
    userDestTemplates: any[] = [];
    userDestDifflists: any[] = [];
    userDestDifflistsRedirectUserSerialId: any = null;
    userVisaWorkflowResources: any[] = [];
    userVisaWorkflowResourcesRedirectUserId: any = null;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<UsersAdministrationRedirectModalComponent>,
        private notify: NotificationService
    ) { }

    async ngOnInit(): Promise<void> {
        await this.getActionInfo();
    }

    async onSubmit() {
        if (this.userDestTemplates.length > 0) {
            await this.updateListmodels();
        }
        if (this.userDestDifflists.length > 0) {
            await this.updateListinstances();
        }
        if (this.userVisaWorkflowResources.length > 0) {
            await this.updateVisaWorkflow();
        }
        if (this.data.user.actionMode === 'delete') {
            await this.deleteUser();
        } else {
            await this.suspendUser();
        }
        this.dialogRef.close('success');
    }

    getActionInfo() {
        return new Promise((resolve) => {
            this.http.get(`../rest/users/${this.data.user.id}/isDeletable`).pipe(
                tap((response: any) => {
                    if (response && response.hasOwnProperty('errors')) {
                        this.notify.error(response.errors);
                        this.dialogRef.close('');
                    } else {
                        this.isDeletable = response.isDeletable;
                        if (this.isDeletable) {
                            this.userDestTemplates = response.listTemplates;
                            this.userDestDifflists = response.listInstances;
                            this.userVisaWorkflowResources = response.workflowListInstances;
                        } else {
                            this.modalTitle = this.data.user.actionMode === 'delete' ? 'lang.unableToDelete' : 'lang.unableToSuspend';
                            this.userDestTemplates = response.listTemplateEntities;
                            this.userDestDifflists = response.listInstanceEntities;
                        }
                        resolve(true);
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    setRedirectUserListModels(index: number, user: any) {
        if (this.data.user.user_id != user.id) {
            this.userDestTemplates[index].redirectUserId = user.id;
        } else {
            this.userDestTemplates[index].redirectUserId = null;
            this.notify.error(this.translate.instant('lang.userUnauthorized'));
        }
    }

    setRedirectUserRes(user: any) {
        if (this.data.user.id !== user.serialId) {
            this.userDestDifflistsRedirectUserSerialId = user.serialId;
        } else {
            this.userDestDifflistsRedirectUserSerialId = null;
            this.notify.error(this.translate.instant('lang.userUnauthorized'));
        }
    }

    setRedirectUserVisaWorkflowRes(user: any) {
        if (this.data.user.user_id != user.id) {
            this.userVisaWorkflowResourcesRedirectUserId = user.id;
        } else {
            this.userVisaWorkflowResourcesRedirectUserId = null;
            this.notify.error(this.translate.instant('lang.userUnauthorized'));
        }
    }

    isValid() {
        let valid = true;

        if (this.userDestTemplates.length > 0) {
            this.userDestTemplates.forEach((element: any) => {
                if (!element.redirectUserId) {
                    valid = false;
                }
            });
        }
        if (this.userDestDifflists.length > 0) {
            if (!this.userDestDifflistsRedirectUserSerialId) {
                valid = false;
            }
        }
        if (this.userVisaWorkflowResources.length > 0) {
            if (!this.userVisaWorkflowResourcesRedirectUserId) {
                valid = false;
            }
        }
        return valid;
    }

    updateListmodels() {
        return new Promise((resolve) => {
            this.http.put(`../rest/listTemplates/entityDest/itemId/${this.data.user.id}`, { redirectListModels: this.userDestTemplates }).pipe(
                tap((data: any) => {
                    if (data != null && data.errors) {
                        this.notify.error(data.errors);
                    } else {
                        resolve(true);
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    updateListinstances() {

        this.replaceDestWithNewUser();

        return new Promise((resolve) => {
            this.http.put('../rest/listinstances', this.userDestDifflists).pipe(
                tap((data: any) => {
                    if (data && data.hasOwnProperty('errors')) {
                        this.notify.error(data.errors);
                    } else {
                        resolve(true);
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    updateVisaWorkflow() {
        this.userVisaWorkflowResources.forEach((res: any, index: number) => {
            this.userVisaWorkflowResources[index].listInstances = this.userVisaWorkflowResources[index].listInstances.map((item: any) => ({
                ...item,
                item_id: (item.process_mode !== null && item.item_id === this.data.user.id) ? this.userVisaWorkflowResourcesRedirectUserId : item.item_id
            }));
        });
        return new Promise((resolve) => {
            this.http.put('../rest/circuits/visaCircuit', { resources: this.userVisaWorkflowResources }).pipe(
                tap((data: any) => {
                    if (data && data.hasOwnProperty('errors')) {
                        this.notify.error(data.errors);
                    } else {
                        resolve(true);
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    deleteUser() {
        return new Promise((resolve) => {
            this.http.delete(`../rest/users/${this.data.user.id}`).pipe(
                tap((data: any) => {
                    this.notify.success(this.translate.instant('lang.userDeleted') + ' « ' + this.data.user.user_id + ' »');
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    suspendUser() {
        return new Promise((resolve) => {
            this.http.put(`../rest/users/${this.data.user.id}/suspend`, this.data.user).pipe(
                tap((data: any) => {
                    this.notify.success(this.translate.instant('lang.userSuspended'));
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    replaceDestWithNewUser() {
        this.userDestDifflists.forEach((res: any, index: number) => {
            this.userDestDifflists[index].listInstances = this.userDestDifflists[index].listInstances.map((item: any) => ({
                ...item,
                item_id: (item.item_mode === 'dest' && item.item_id === this.data.user.id) ? this.userDestDifflistsRedirectUserSerialId : item.item_id
            }));
        });
    }
}
