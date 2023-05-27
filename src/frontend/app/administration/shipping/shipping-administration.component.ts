import { Component, OnInit, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatSidenav } from '@angular/material/sidenav';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { ActivatedRoute, Router } from '@angular/router';
import { AppService } from '@service/app.service';
import { catchError, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { MaarchFlatTreeComponent } from '../../../plugins/tree/maarch-flat-tree.component';

@Component({
    templateUrl: 'shipping-administration.component.html',
    styleUrls: ['shipping-administration.component.scss']
})
export class ShippingAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('maarchTree', { static: true }) maarchTree: MaarchFlatTreeComponent;

    loading: boolean = false;
    creationMode: boolean = true;

    shipping: any = {
        label: '',
        description: '',
        options: {
            shapingOptions: ['addressPage'],
            sendMode: 'fast',
        },
        fee: {
            firstPagePrice: 0,
            nextPagePrice: 0,
            postagePrice: 0,
        },
        account: {
            id: '',
            password: ''
        },
        entities: []
    };

    entities: any[] = [];
    entitiesClone: any = null;
    shippingClone: any = null;

    shapingOptions: string[] = [
        'color',
        'duplexPrinting',
        'addressPage',
    ];

    sendModes: string[] = [
        'digital_registered_mail',
        'digital_registered_mail_with_AR',
        'fast',
        'economic'
    ];
    hidePassword: boolean = true;

    shippingAvailable: boolean = false;



    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService
    ) { }

    ngOnInit(): void {
        this.http.get('../rest/externalConnectionsEnabled').pipe(
            tap((data: any) => {
                this.shippingAvailable = data.connection.maileva === true;
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();

        this.route.params.subscribe(params => {
            if (typeof params['id'] === 'undefined') {
                this.headerService.setHeader(this.translate.instant('lang.shippingCreation'));

                this.creationMode = true;

                this.http.get('../rest/administration/shippings/new')
                    .subscribe((data: any) => {
                        this.entities = data['entities'].map(
                            (item: any) => ({
                                ...item,
                                id : parseInt(item.id)
                            })
                        );
                        this.entitiesClone = JSON.parse(JSON.stringify(this.entities));
                        this.initEntitiesTree(this.entities);
                        this.shippingClone = JSON.parse(JSON.stringify(this.shipping));
                        this.loading = false;
                    }, (err) => {
                        this.notify.handleErrors(err);
                    });

                this.loading = false;

            } else {
                this.headerService.setHeader(this.translate.instant('lang.shippingModification'));
                this.creationMode = false;

                this.http.get('../rest/administration/shippings/' + params['id'])
                    .subscribe((data: any) => {
                        this.shipping = data['shipping'];
                        this.entities = data['entities'];
                        this.entitiesClone = JSON.parse(JSON.stringify(this.entities));
                        this.initEntitiesTree(this.entities);
                        this.shippingClone = JSON.parse(JSON.stringify(this.shipping));
                        this.loading = false;
                    }, (err) => {
                        this.notify.handleErrors(err);
                    });

            }
        });
    }

    initEntitiesTree(entities: any) {
        this.maarchTree.initData(entities);
    }

    updateSelectedEntities() {
        this.shipping.entities = this.maarchTree.getSelectedNodes().map(ent => ent.id);
    }

    onSubmit() {
        this.loading = true;
        if (this.creationMode) {
            this.http.post('../rest/administration/shippings', this.shipping)
                .subscribe((data: any) => {
                    this.shippingClone = JSON.parse(JSON.stringify(this.shipping));
                    this.notify.success(this.translate.instant('lang.shippingAdded'));
                    this.router.navigate(['/administration/shippings']);
                }, (err) => {
                    this.notify.handleErrors(err);
                });
        } else {
            this.http.put('../rest/administration/shippings/' + this.shipping.id, this.shipping)
                .subscribe((data: any) => {
                    this.shippingClone = JSON.parse(JSON.stringify(this.shipping));
                    this.notify.success(this.translate.instant('lang.shippingUpdated'));
                    this.router.navigate(['/administration/shippings']);
                }, (err) => {
                    this.notify.handleErrors(err);
                });
        }
        this.loading = false;
    }

    checkModif() {
        return (JSON.stringify(this.shippingClone) === JSON.stringify(this.shipping));
    }

    toggleShapingOption(option: string) {
        const index = this.shipping.options.shapingOptions.indexOf(option);
        if (index > -1) {
            this.shipping.options.shapingOptions.splice(index, 1);
        } else {
            this.shipping.options.shapingOptions.push(option);
        }
    }

    cancelModification() {
        this.shipping = JSON.parse(JSON.stringify(this.shippingClone));
        this.entities = JSON.parse(JSON.stringify(this.entitiesClone));
        this.initEntitiesTree(this.entities);
    }
}
