import { Component, OnInit, ViewChild, EventEmitter, Input, Output } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { MatDialog } from '@angular/material/dialog';
import { MatSort } from '@angular/material/sort';
import { MatTableDataSource } from '@angular/material/table';
import { map, catchError, filter, tap } from 'rxjs/operators';
import { FunctionsService } from '@service/functions.service';
import { PrivilegeService } from '@service/privileges.service';
import { SentResourcePageComponent } from './sent-resource-page/sent-resource-page.component';
import { SentNumericPackagePageComponent } from './sent-numeric-package-page/sent-numeric-package-page.component';
import { ShippingModalComponent } from './shippingModal/shipping-modal.component';

@Component({
    selector: 'app-sent-resource-list',
    templateUrl: 'sent-resource-list.component.html',
    styleUrls: ['sent-resource-list.component.scss'],
})
export class SentResourceListComponent implements OnInit {

    @Input('resId') resId: number = null;
    @Input() editMode: boolean = true;
    @Input() currentUserId: number = null;
    @Input() currentGroupId: number = null;
    @Input() currentBasketId: number = null;
    @Input() injectDatas: any;

    @Output() reloadBadgeSentResource = new EventEmitter<string>();

    @ViewChild(MatSort, { static: false }) sort: MatSort;

    loading: boolean = true;

    dataSource: any;
    displayedColumns: string[] = ['creationDate'];

    sentResources: any[] = [];

    resultsLength = 0;

    currentFilter: string = '';
    filterTypes: any[] = [];



    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialog: MatDialog,
        public functions: FunctionsService,
        public privilegeService: PrivilegeService) { }

    async ngOnInit(): Promise<void> {
        this.loadList();
    }

    async loadList() {
        this.sentResources = [];
        this.loading = true;
        await this.initAcknowledgementReceiptList();
        await this.initEmailList();
        await this.initMessageExchange();
        await this.initShippings();
        this.reloadBadgeSentResource.emit(`${this.sentResources.length}`);

        this.initFilter();

        setTimeout(() => {
            this.dataSource = new MatTableDataSource(this.sentResources);
            this.dataSource.sort = this.sort;
        }, 0);
        this.loading = false;
    }

    initAcknowledgementReceiptList() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.resId}/acknowledgementReceipts?type=ar`).pipe(
                map((data: any) => {
                    data = data.map((item: any) => {
                        let email;
                        if (!this.functions.empty(item.contact.email)) {
                            email = item.contact.email;
                        } else {
                            email = this.translate.instant('lang.withoutEmail');
                        }
                        let name;
                        if (!this.functions.empty(item.contact.firstname) || !this.functions.empty(item.contact.lastname)) {
                            name = item.contact.firstname + ' ' + item.contact.lastname;
                        } else if (!this.functions.empty(item.contact.company)) {
                            name = item.contact.company;
                        } else {
                            name = this.translate.instant('lang.contactDeleted');
                        }

                        return {
                            id: item.id,
                            sender: false,
                            recipients: item.format === 'html' ? email : name,
                            creationDate: item.creationDate,
                            sendDate: item.sendDate,
                            type: 'acknowledgementReceipt',
                            typeColor: '#7d5ba6',
                            desc: item.format === 'html' ? this.translate.instant('lang.ARelectronic') : this.translate.instant('lang.ARPaper'),
                            status: item.format === 'html' && item.sendDate === null ? 'ERROR' : 'SENT',
                            hasAttach: false,
                            hasNote: false,
                            hasMainDoc: false,
                            canManage: true
                        };
                    });
                    return data;
                }),
                tap((data: any) => {
                    this.sentResources = this.sentResources.concat(data);

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

    initEmailList() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.resId}/emails?type=email`).pipe(
                map((data: any) => {
                    data.emails = data.emails.map((item: any) => ({
                        id: item.id,
                        sender: item.sender.email,
                        recipients: item.recipients,
                        creationDate: item.creation_date,
                        sendDate: item.send_date,
                        type: 'email',
                        typeColor: '#5bc0de',
                        desc: !this.functions.empty(item.object) ? item.object : `<i>${this.translate.instant('lang.emptySubject')}<i>`,
                        status: item.status,
                        hasAttach: !this.functions.empty(item.document.attachments),
                        hasNote: !this.functions.empty(item.document.notes),
                        hasMainDoc: item.document.isLinked,
                        canManage: true
                    }));
                    return data.emails;
                }),
                tap((data: any) => {
                    this.sentResources = this.sentResources.concat(data);

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

    initMessageExchange() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.resId}/messageExchanges`).pipe(
                map((data: any) => {
                    data.messageExchanges = data.messageExchanges.map((item: any) => ({
                        id: item.messageId,
                        sender: item.sender,
                        recipients: item.recipient,
                        creationDate: item.creationDate,
                        sendDate: item.receptionDate,
                        operationDate: item.operationDate,
                        type: 'm2m_ARCHIVETRANSFER',
                        typeColor: '#F99830',
                        desc: this.translate.instant('lang.m2m_ARCHIVETRANSFER'),
                        status: item.status.toUpperCase(),
                        hasAttach: false,
                        hasNote: false,
                        hasMainDoc: false,
                        canManage: true
                    }));
                    return data.messageExchanges;
                }),
                tap((data: any) => {
                    this.sentResources = this.sentResources.concat(data);

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

    initShippings() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.resId}/shippings`).pipe(
                map((data: any) => {
                    data = data.map((item: any) => ({
                        id: item.id,
                        sender: item.userLabel,
                        recipients: item.recipients,
                        creationDate: item.creationDate,
                        sendDate: item.creationDate,
                        type: 'shipping',
                        typeColor: '#9440D5',
                        desc: this.functions.empty(item.chrono) ? this.translate.instant('lang.shipping') : `[${item.chrono}] ` + this.translate.instant('lang.shipping'),
                        status: 'SENT',
                        hasAttach: !this.functions.empty(item.attachments),
                        hasNote: !this.functions.empty(item.notes),
                        hasMainDoc: item.isLinked,
                        canManage: true
                    }));
                    return data;
                }),
                tap((data: any) => {
                    data.forEach((element: any) => {
                        element.recipients.forEach((el: any, index: number) => {
                            // convert object to array to use filter function
                            el = Object.values(el);
                            element.recipients[index] = el.filter((item: any) => item !== '');
                        });
                    });
                    this.sentResources = this.sentResources.concat(data);

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

    initFilter() {
        this.sentResources.forEach((element: any) => {
            if (this.filterTypes.filter(type => type.id === element.type).length === 0) {
                this.filterTypes.push({
                    id: element.type,
                    label: this.translate.instant('lang.' + element.type)
                });
            }
        });
    }

    filterType(ev: any) {
        this.currentFilter = ev.value;
        this.dataSource.filter = ev.value;
    }

    open(row: any = {id: null, type: null}) {

        if (row.type === 'm2m_ARCHIVETRANSFER') {
            this.openPromptNumericPackage(row);
        } else {
            this.openPromptMail(row);
        }
    }

    async openPromptMail(row: any = {id: null, type: null}) {
        let title = this.translate.instant('lang.sendElement');

        if (row.id !== null) {
            title = this.translate.instant('lang.' + row.type);
        }
        if (row.type !== 'shipping') {
            if (row.canManage || row.id === null) {
                const dialogRef = this.dialog.open(SentResourcePageComponent, {
                    panelClass: 'maarch-modal', width: '60vw', disableClose: true, data: {
                        title: title,
                        resId: this.resId,
                        emailId: row.id, emailType: row.type,
                        currentUserId: this.currentUserId,
                        currentGroupId: this.currentGroupId,
                        currentBasketId: this.currentBasketId,
                    }
                });

                dialogRef.afterClosed().pipe(
                    filter((data: any) => data.state === 'success' || data === 'success'),
                    tap(() => {
                        this.refreshEmailList();
                        this.reloadBadgeSentResource.emit(`${this.sentResources.length}`);
                        setTimeout(() => {
                            this.refreshWaitingElements(1);
                            setTimeout(() => {
                                this.dataSource = new MatTableDataSource(this.sentResources);
                                this.dataSource.sort = this.sort;
                            }, 0);
                        }, 5000);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
            }
        } else if (row.type === 'shipping') {
            const dialogRef = this.dialog.open(ShippingModalComponent, {
                panelClass: 'maarch-modal', width: '60vw', disableClose: true,
                data: {
                    title: title,
                    shippingData: row
                }
            });
        }
    }

    openPromptNumericPackage(row: any = {id: null, type: null}) {

        let title = this.translate.instant('lang.sendElement');

        if (row.id !== null) {
            title = this.translate.instant('lang.' + row.type);
        }

        if (row.canManage || row.id === null) {
            const dialogRef = this.dialog.open(SentNumericPackagePageComponent, { panelClass: 'maarch-modal', width: '60vw', disableClose: true, data: { title: title, resId: this.resId, emailId: row.id } });

            dialogRef.afterClosed().pipe(
                filter((data: any) => data.state === 'success' || data === 'success'),
                tap(() => {
                    this.loadList();
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
            this.reloadBadgeSentResource.emit(`${this.sentResources.length}`);
        }
    }

    refreshWaitingElements(countTry: number) {
        if (countTry < 6) {
            this.sentResources.forEach((draftElement: any) => {
                if (draftElement.status == 'WAITING' && draftElement.type == 'email') {
                    this.http.get(`../rest/emails/${draftElement.id}`).pipe(
                        tap((data: any) => {
                            if (data.status == 'SENT' || data.status == 'ERROR') {
                                if (data.status == 'SENT') {
                                    this.notify.success(this.translate.instant('lang.emailSent'));
                                } else {
                                    this.notify.error(this.translate.instant('lang.emailCannotSent'));
                                }
                                this.sentResources.forEach((element: any, key: number) => {
                                    if (element.id == draftElement.id && element.type == 'email') {
                                        this.sentResources[key].status = data.status;
                                        this.sentResources[key].sendDate = data.sendDate;
                                    }
                                });
                            } else if (data.status == 'WAITING') {
                                setTimeout(() => {
                                    this.refreshWaitingElements(countTry + 1);
                                }, 5000);
                            }
                        })
                    ).subscribe();
                }
            });
        }
    }

    refreshEmailList() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.resId}/emails?type=email`).pipe(
                map((data: any) => {
                    data.emails = data.emails.map((item: any) => ({
                        id: item.id,
                        sender: item.sender.email,
                        recipients: item.recipients,
                        creationDate: item.creation_date,
                        sendDate: item.send_date,
                        type: 'email',
                        typeColor: '#5bc0de',
                        desc: !this.functions.empty(item.object) ? item.object : `<i>${this.translate.instant('lang.emptySubject')}<i>`,
                        status: item.status,
                        hasAttach: !this.functions.empty(item.document.attachments),
                        hasNote: !this.functions.empty(item.document.notes),
                        hasMainDoc: item.document.isLinked,
                        canManage: true
                    }));
                    return data.emails;
                }),
                tap((data: any) => {
                    const sentResourcesNoEmails = this.sentResources.filter(elem => elem.type !== 'email');
                    this.sentResources = sentResourcesNoEmails.concat(data);
                    this.reloadBadgeSentResource.emit(`${this.sentResources.length}`);
                    setTimeout(() => {
                        this.dataSource = new MatTableDataSource(this.sentResources);
                        this.dataSource.sort = this.sort;
                    }, 0);
                    this.initFilter();
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

    canSendNumericPackage() {
        return this.privilegeService.getCurrentUserMenus().filter((item: any) => item.id === 'manage_numeric_package').length > 0;
    }
}
