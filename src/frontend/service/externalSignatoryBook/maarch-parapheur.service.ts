import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FunctionsService } from '@service/functions.service';
import { catchError, of, tap } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { TranslateService } from '@ngx-translate/core';
import { UserWorkflow } from '@models/user-workflow.model';

@Injectable({
    providedIn: 'root'
})

export class MaarchParapheurService {

    autocompleteUsersRoute: string = '/rest/autocomplete/maarchParapheurUsers';

    canCreateUser: boolean = true;
    canSynchronizeSignatures: boolean = true;
    canViewWorkflow: boolean = true;
    canCreateTile: boolean = true;
    canAddExternalUser: boolean = true;
    canManageSignaturesPositions: boolean = true;

    otpConnectors: any [] = [];

    constructor(
        public functions: FunctionsService,
        public translate: TranslateService,
        private http: HttpClient,
        private notify: NotificationService
    ) { }

    getWorkflowDetails(): Promise<any> {
        return new Promise((resolve) => {
            resolve(null);
        });
    }

    loadListModel(entityId: number): Promise<any> {
        return new Promise((resolve) => {
            this.http.get(`../rest/listTemplates/entities/${entityId}?type=visaCircuit&maarchParapheur=true`).pipe(
                tap((data: any) => {
                    resolve(data);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(null);
                    return of(false);
                })
            ).subscribe();
        });
    }

    loadWorkflow(resId: number, type: string): Promise<any> {
        return new Promise((resolve) => {
            this.http.get(`../rest/documents/${resId}/maarchParapheurWorkflow?type=${type}`).pipe(
                tap((data: any) => {
                    resolve(data);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(null);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getUserAvatar(externalId: any): Promise<any> {
        if (typeof externalId === 'number') {
            return new Promise((resolve) => {
                this.http.get(`../rest/maarchParapheur/user/${externalId}/picture`).pipe(
                    tap((data: any) => {
                        resolve(data.picture);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        resolve(null);
                        return of(false);
                    })
                ).subscribe();
            });
        } else if (typeof externalId === 'string') {
            return new Promise((resolve) => {
                this.http.get(`assets/${externalId}.png`, { responseType: 'blob' }).pipe(
                    tap((response: any) => {
                        const reader = new FileReader();
                        reader.readAsDataURL(response);
                        reader.onloadend = () => {
                            resolve(reader.result as any);
                        };
                    }),
                    catchError(err => {
                        this.notify.handleErrors(err);
                        return of(false);
                    })
                ).subscribe();
            });
        }
    }

    getOtpConfig(): Promise<any> {
        return new Promise((resolve) => {
            this.http.get('../rest/maarchParapheurOtp').pipe(
                tap((data: any) => {
                    this.otpConnectors = data.otp;
                    resolve(this.otpConnectors);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(null);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getAutocompleteDatas(data: any): Promise<any> {
        return new Promise((resolve) => {
            this.http.get(`..${this.autocompleteUsersRoute}`, { params: { 'search': data.user.mail, 'excludeAlreadyConnected': 'true' } })
                .pipe(
                    tap((result: any) => {
                        resolve(result);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        resolve(null);
                        return of(false);
                    })
                ).subscribe();
        });
    }

    linkAccountToSignatoryBook(data: any, serialId: number): Promise<any> {
        return new Promise((resolve) => {
            this.http.put(`../rest/users/${serialId}/linkToMaarchParapheur`, { maarchParapheurUserId: data.id }).pipe(
                tap(() => {
                    this.notify.success(this.translate.instant('lang.accountLinked'));
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    unlinkSignatoryBookAccount(serialId: number): Promise<any> {
        return new Promise((resolve) => {
            this.http.put(`../rest/users/${serialId}/unlinkToMaarchParapheur`, {}).pipe(
                tap(() => {
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    createExternalSignatoryBookAccount(id: number, login: string, serialId: number): Promise<any> {
        return new Promise((resolve) => {
            this.http.put(`../rest/users/${id}/createInMaarchParapheur`, { login: login }).pipe(
                tap((data: any) => {
                    this.notify.success(this.translate.instant('lang.accountAdded'));
                    resolve(data);
                }),
                catchError((err: any) => {
                    if (err.error.errors === 'Login already exists') {
                        this.translate.instant('lang.loginAlreadyExistsInMaarchParapheur');
                    } else {
                        this.notify.handleSoftErrors(err);
                    }
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    checkInfoExternalSignatoryBookAccount(serialId: number): Promise<any> {
        return new Promise((resolve) => {
            this.http.get('../rest/users/' + serialId + '/statusInMaarchParapheur').pipe(
                tap((data: any) => {
                    resolve(data);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(null);
                    return of(false);
                })
            ).subscribe();
        });
    }

    setExternalInformation(item: any): UserWorkflow {
        return item;
    }

    getRessources(additionalsInfos: any): any[] {
        return additionalsInfos.attachments.map((e: any) => e.res_id);
    }

    isValidParaph(additionalsInfos: any = null, workflow: any[] = [], resourcesToSign = [], userOtps = []) {
        if (additionalsInfos.attachments.length === 0 || workflow.length === 0 || userOtps.length > 0 || resourcesToSign.length === 0) {
            return false;
        } else {
            return true;
        }
    }

    synchronizeSignatures(data: any) {
        return new Promise((resolve) => {
            this.http.put(`../rest/users/${data.id}/externalSignatures`, {}).pipe(
                tap((result: any) => {
                    this.notify.success(this.translate.instant('lang.signsSynchronized'));
                    resolve(result);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(null);
                    return of(false);
                })
            ).subscribe();
        });
    }
}
