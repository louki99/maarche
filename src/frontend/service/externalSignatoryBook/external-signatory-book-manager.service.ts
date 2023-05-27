import { Injectable, Injector } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { catchError, of, tap } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { MaarchParapheurService } from './maarch-parapheur.service';
import { FastParapheurService } from './fast-parapheur.service';
import { TranslateService } from '@ngx-translate/core';
import { AuthService } from '@service/auth.service';
import { FunctionsService } from '@service/functions.service';
import { ResourceStep } from '@models/resource-step.model';
@Injectable()

export class ExternalSignatoryBookManagerService {

    allowedSignatoryBook: string[] = ['maarchParapheur', 'fastParapheur'];
    serviceInjected: MaarchParapheurService | FastParapheurService;
    signatoryBookEnabled: string = '';

    constructor(
        private injector: Injector,
        private http: HttpClient,
        private notifications: NotificationService,
        private translate: TranslateService,
        private authService: AuthService,
        private functions: FunctionsService
    ) {
        if (this.allowedSignatoryBook.indexOf(this.authService.externalSignatoryBook?.id) > -1) {
            if (this.authService.externalSignatoryBook?.id === 'maarchParapheur') {
                this.signatoryBookEnabled = this.authService.externalSignatoryBook?.id;
                this.serviceInjected = this.injector.get<MaarchParapheurService>(MaarchParapheurService);
            } else if (this.authService.externalSignatoryBook?.id === 'fastParapheur' && this.authService.externalSignatoryBook?.integratedWorkflow) {
                this.signatoryBookEnabled = this.authService.externalSignatoryBook?.id;
                this.serviceInjected = this.injector.get<FastParapheurService>(FastParapheurService);
            }
        }
    }

    checkExternalSignatureBook(data: any): Promise<any> {
        return new Promise((resolve) => {
            this.http.post(`../rest/resourcesList/users/${data.userId}/groups/${data.groupId}/baskets/${data.basketId}/checkExternalSignatoryBook`, { resources: data.resIds }).pipe(
                tap((result: any) => {
                    resolve(result);
                }),
                catchError((err: any) => {
                    this.notifications.handleSoftErrors(err);
                    resolve(null);
                    return of(false);
                })
            ).subscribe();

        });
    }

    getWorkflowDetails(): Promise<any> {
        return this.serviceInjected?.getWorkflowDetails();
    }

    loadListModel(entityId: number) {
        return this.serviceInjected?.loadListModel(entityId);
    }

    loadWorkflow(attachmentId: number, type: string) {
        return this.serviceInjected?.loadWorkflow(attachmentId, type);
    }

    getUserAvatar(externalId: any) {
        return this.serviceInjected?.getUserAvatar(externalId);
    }

    getOtpConfig(): Promise<any> {
        return this.serviceInjected?.getOtpConfig();
    }

    getAutocompleteUsersRoute(): string {
        return this.serviceInjected?.autocompleteUsersRoute;
    }

    isValidExtWorkflow(workflow: any[]): boolean {
        let res: boolean = true;
        workflow.forEach((item: any, indexUserRgs: number) => {
            if (['visa', 'stamp'].indexOf(item.role) === -1) {
                if (workflow.filter((itemUserStamp: any, indexUserStamp: number) => indexUserStamp > indexUserRgs && itemUserStamp.role === 'stamp').length > 0) {
                    res = false;
                }
            } else {
                return true;
            }
        });
        return res;
    }

    getAutocompleteUsersDatas(data: any) {
        return this.serviceInjected?.getAutocompleteDatas(data);
    }

    linkAccountToSignatoryBook(data: any, serialId: number) {
        return this.serviceInjected?.linkAccountToSignatoryBook(data, serialId);
    }

    unlinkSignatoryBookAccount(serialId: number) {
        return this.serviceInjected?.unlinkSignatoryBookAccount(serialId);
    }

    createExternalSignatoryBookAccount(id: number, login: string, serialId: number) {
        return this.serviceInjected?.createExternalSignatoryBookAccount(id, login, serialId);
    }

    checkInfoExternalSignatoryBookAccount(serialId: number) {
        return this.serviceInjected?.checkInfoExternalSignatoryBookAccount(serialId);
    }

    setExternalInformation(item: any) {
        return this.serviceInjected?.setExternalInformation(item);
    }

    isValidParaph(additionalsInfos: any = null, workflow: any[] = [], resourcesToSign = [], userOtps = []) {
        return this.serviceInjected?.isValidParaph(additionalsInfos, workflow, resourcesToSign, userOtps);
    }

    getRessources(additionalsInfos: any): any[] {
        return this.serviceInjected?.getRessources(additionalsInfos);
    }

    getDatas(workflow: any[] = [], resourcesToSign: any[] = [], workflowType: any): any {
        const formatedData: any = { steps: [] };
        resourcesToSign.forEach((resource: any) => {
            workflow.forEach((element: any, index: number) => {
                const step: ResourceStep = {
                    'resId': resource.resId,
                    'mainDocument': resource.mainDocument,
                    'externalId': element.externalId[this.signatoryBookEnabled],
                    'sequence': index,
                    'action': this.getWorkflowAction(element.role),
                    'signatureMode': element.role,
                    'signaturePositions': element.signaturePositions !== undefined ? this.formatPositions(element.signaturePositions.filter((pos: any) => pos.resId === resource.resId && pos.mainDocument === resource.mainDocument)) : [],
                    'datePositions': element.datePositions !== undefined ? this.formatPositions(element.datePositions.filter((pos: any) => pos.resId === resource.resId && pos.mainDocument === resource.mainDocument)) : [],
                    'externalInformations': element.hasOwnProperty('externalInformations') ? element.externalInformations : null
                };
                formatedData['steps'].push(step);
            });
        });
        if (this.signatoryBookEnabled === 'fastParapheur') {
            formatedData['workflowType'] = workflowType;
        }
        return formatedData;
    }

    getWorkflowAction(role: string): string {
        if (role === 'note') {
            return 'note';
        }
        return role === 'visa' ? 'visa' : 'sign';
    }

    formatPositions(position: any): any {
        delete position.mainDocument;
        delete position.resId;
        return position;
    }

    canCreateUser(): boolean {
        return this.serviceInjected?.canCreateUser;
    }

    async synchronizeSignatures(data: any) {
        await this.serviceInjected?.synchronizeSignatures(data);
    }

    canSynchronizeSignatures(): boolean {
        return this.serviceInjected?.canSynchronizeSignatures;
    }

    canViewWorkflow(): boolean {
        return this.serviceInjected?.canViewWorkflow;
    }

    canCreateTile(): boolean {
        return this.serviceInjected?.canCreateTile;
    }

    isLinkedToExternalSignatoryBook(): Promise<any> {
        return new Promise((resolve) => {
            this.http.get('../rest/home').pipe(
                tap((data: any) => {
                    resolve(data);
                }),
                catchError((err: any) => {
                    this.notifications.handleSoftErrors(err);
                    resolve(null);
                    return of(false);
                })
            ).subscribe();
        });
    }

    canAddExternalUser(): boolean {
        return this.serviceInjected?.canAddExternalUser;
    }

    canManageSignaturesPositions(): boolean {
        return this.serviceInjected?.canManageSignaturesPositions;
    }

    getOtpConnectors(): any[] {
        return this.serviceInjected?.otpConnectors;
    }
}
