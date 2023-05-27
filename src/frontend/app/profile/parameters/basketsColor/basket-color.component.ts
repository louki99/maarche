import { Component, Input, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { TranslateService } from '@ngx-translate/core';
import { FunctionsService } from '@service/functions.service';
import { HeaderService } from '@service/header.service';

@Component({
    selector: 'app-basket-color',
    templateUrl: './basket-color.component.html',
    styleUrls: ['./basket-color.component.scss'],
})

export class BasketColorComponent implements OnInit {

    @Input() userGroupBaskets: any;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public functionsService: FunctionsService,
        public headerService: HeaderService,

    ) {}

    ngOnInit(): void {}

    updateBasketColor(i: number, y: number) {
        this.http.put('../rest/currentUser/groups/' + this.userGroupBaskets[i].groupSerialId + '/baskets/' + this.userGroupBaskets[i].baskets[y].basket_id, { 'color': this.userGroupBaskets[i].baskets[y].color })
            .subscribe((data: any) => {
                this.userGroupBaskets = data.userBaskets;
                this.notify.success(this.translate.instant('lang.modificationSaved'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }
}
