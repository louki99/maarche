import { Component, OnInit, Input } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HttpClient } from '@angular/common/http';

@Component({
    selector: 'app-i-paraph',
    templateUrl: 'i-paraph.component.html',
    styleUrls: ['i-paraph.component.scss'],
})
export class IParaphComponent implements OnInit {

    @Input() additionalsInfos: any;
    @Input() externalSignatoryBookDatas: any;

    loading: boolean = false;

    currentAccount: any = null;
    usersWorkflowList: any[] = [];

    injectDatasParam = {
        resId: 0,
        editable: true
    };

    constructor(public translate: TranslateService, public http: HttpClient, private notify: NotificationService) { }

    ngOnInit(): void {
    }

    isValidParaph() {
        if (this.additionalsInfos.attachments.length === 0) {
            return false;
        } else {
            return true;
        }
    }

    getRessources() {
        return this.additionalsInfos.attachments.map((e: any) => e.res_id);
    }

    getDatas() {
        return this.externalSignatoryBookDatas;
    }
}
