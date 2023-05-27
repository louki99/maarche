import { Component, OnInit, Input } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { UntypedFormControl } from '@angular/forms';
import { LocalStorageService } from '@service/local-storage.service';
import { HeaderService } from '@service/header.service';
import { catchError, tap } from 'rxjs/operators';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';

@Component({
    selector: 'app-ixbus-paraph',
    templateUrl: 'ixbus-paraph.component.html',
    styleUrls: ['ixbus-paraph.component.scss'],
})
export class IxbusParaphComponent implements OnInit {

    @Input() additionalsInfos: any;
    @Input() externalSignatoryBookDatas: any;

    loading: boolean = true;

    currentAccount: any = null;
    usersWorkflowList: any[] = [];
    natures: any[] = [];
    messagesModel: any[] = [];
    users: any[] = [];
    ixbusDatas: any = {
        nature: '',
        messageModel: '',
        userId: '',
        signatureMode: 'manual'
    };

    injectDatasParam = {
        resId: 0,
        editable: true
    };

    selectNature = new UntypedFormControl();
    selectWorkflow = new UntypedFormControl();
    selectUser = new UntypedFormControl();

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public headerService: HeaderService,
        public functions: FunctionsService,
        private localStorage: LocalStorageService,
        private notifications: NotificationService
    ) { }

    ngOnInit(): void {
        this.additionalsInfos.ixbus.natures.forEach((element: any) => {
            this.natures.push({id: element.identifiant, label: element.nom});
        });

        if (this.localStorage.get(`ixBusSignatureMode_${this.headerService.user.id}`) !== null) {
            this.ixbusDatas.signatureMode = this.localStorage.get(`ixBusSignatureMode_${this.headerService.user.id}`);
        }

        this.loading = false;
    }

    changeModel(natureId: string) {
        this.http.get(`../rest/ixbus/natureDetails/${natureId}`).pipe(
            tap((data: any) => {
                if (!this.functions.empty(data.messageModels)) {
                    this.messagesModel = data.messageModels.map((message: any) => ({
                        id: message.identifiant,
                        label: message.nom
                    }));
                }
                if (!this.functions.empty(data.users)) {
                    this.users = data.users.map((user: any) => ({
                        id: user.identifiant,
                        label: `${user.prenom} ${user.nom}`
                    }));
                }
            }),
            catchError((err: any) => {
                this.notifications.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isValidParaph() {
        if (this.additionalsInfos.attachments.length === 0 || this.natures.length === 0 || this.messagesModel.length === 0 || this.users.length === 0 || !this.ixbusDatas.nature
            || !this.ixbusDatas.messageModel || !this.ixbusDatas.userId) {
            return false;
        } else {
            return true;
        }
    }

    getRessources() {
        return this.additionalsInfos.attachments.map((e: any) => e.res_id);
    }

    getDatas() {
        this.localStorage.save(`ixBusSignatureMode_${this.headerService.user.id}`, this.ixbusDatas.signatureMode);
        this.externalSignatoryBookDatas = {
            'ixbus': this.ixbusDatas,
            'steps': []
        };
        return this.externalSignatoryBookDatas;
    }
}
