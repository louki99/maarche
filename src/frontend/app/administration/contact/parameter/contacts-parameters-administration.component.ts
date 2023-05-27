import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { MatTableDataSource } from '@angular/material/table';
import { FunctionsService } from '@service/functions.service';
import { ContactService } from '@service/contact.service';
import { catchError, debounceTime, exhaustMap, filter, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { KeyValue } from '@angular/common';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';
import { UntypedFormControl } from '@angular/forms';

@Component({
    templateUrl: 'contacts-parameters-administration.component.html',
    styleUrls: ['contacts-parameters-administration.component.scss'],
    providers: [ContactService]
})
export class ContactsParametersAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    subMenus: any[] = [];

    contactsFilling: any = {
        'enable': false,
        'first_threshold': '33',
        'second_threshold': '66',
    };

    contactsParameters: any = [];

    arrRatingColumns: String[] = [];
    fillingColor = {
        'first_threshold': '#E81C2B',
        'second_threshold': '#F4891E',
        'third_threshold': '#0AA34F',
    };
    civilities: any[] = [];

    loading: boolean = false;

    dataSource = new MatTableDataSource(this.contactsParameters);
    displayedColumns = ['label', 'mandatory', 'filling', 'searchable', 'displayable'];

    sectorMsg: string = '';

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public functionsService: FunctionsService,
        public contactService: ContactService,
        private viewContainerRef: ViewContainerRef,
        private dialog: MatDialog,
    ) {
        this.subMenus = contactService.getAdminMenu();
    }

    ngOnInit(): void {

        this.loading = true;

        this.headerService.setHeader(this.translate.instant('lang.contactsParameters'));
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.getCivilities();
        this.http.get('../rest/contactsParameters')
            .subscribe((data: any) => {
                this.contactsFilling = data.contactsFilling;
                this.contactsParameters = data.contactsParameters.map((item: any) => ({
                    ...item,
                    label : this.functionsService.empty(item.label) ? this.translate.instant('lang.contactsParameters_' + item.identifier) : item.label
                }));
                this.sectorMsg = this.contactsParameters.find((param: any) => param.identifier === 'sector' && param.displayable) ? this.translate.instant('lang.sectorMsg') : '';
                this.loading = false;
                setTimeout(() => {
                    this.dataSource = new MatTableDataSource(this.contactsParameters);
                    this.dataSource.paginator = this.paginator;
                    this.dataSource.sort = this.sort;
                }, 0);
            });
    }

    getCivilities() {
        this.http.get('../rest/civilities').pipe(
            tap((data: any) => {
                data.civilities.forEach((civility: any, index: number) => {
                    this.civilities[index] = {};
                    Object.keys(civility).forEach((elementId: any) => {
                        this.civilities[index][elementId] = new UntypedFormControl(civility[elementId]);
                        if (elementId === 'id') {
                            this.civilities[index][elementId].disable();
                        } else {
                            this.civilities[index][elementId].valueChanges
                                .pipe(
                                    debounceTime(1000),
                                    tap(() => {
                                        this.updateCivility(this.civilities[index]);
                                    }),
                                ).subscribe();
                        }
                    });
                });
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    updateCivility(civility: any) {
        this.http.put(`../rest/civilities/${civility.id.value}`, this.formatCivilityData(civility)).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.civilityUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    formatCivilityData(data: any) {
        const obj: any = {};
        Object.keys(data).forEach(elemId => {
            obj[elemId] = data[elemId].value;
        });
        return obj;
    }

    addCivility() {
        const newCivility: any = {
            id: null,
            label: 'label',
            abbreviation: 'abbreviation'
        };
        this.http.post('../rest/civilities', newCivility).pipe(
            tap((data: any) => {
                newCivility.id = data.id;
                Object.keys(newCivility).forEach((elementId: any) => {
                    newCivility[elementId] = new UntypedFormControl(newCivility[elementId]);
                    if (elementId === 'id') {
                        newCivility[elementId].disable();
                    } else {
                        newCivility[elementId].valueChanges
                            .pipe(
                                debounceTime(1000),
                                tap(() => {
                                    this.updateCivility(newCivility);
                                }),
                            ).subscribe();
                    }
                });
                this.civilities.push(newCivility);
                this.notify.success(this.translate.instant('lang.civilityAdded'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }


    deleteCivility(civility: any, index: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete(`../rest/civilities/${civility.id.value}`)),
            tap(() => {
                this.civilities.splice(index, 1);
                this.notify.success(this.translate.instant('lang.civilityDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    addCriteria(event: any, criteria: any, type: string) {
        if (criteria.identifier === 'sector' && type === 'displayable') {
            this.sectorMsg = event.checked === true ? this.translate.instant('lang.sectorMsg') : '';
        }
        this.contactsParameters.forEach((col: any, i: number) => {
            if (col.id === criteria.id) {
                this.contactsParameters[i][type] = event.checked;
            }
        });

        this.onSubmit();
    }

    onSubmit() {
        if (this.contactsFilling.first_threshold >= this.contactsFilling.second_threshold) {
            this.contactsFilling.second_threshold = this.contactsFilling.first_threshold + 1;
        }
        this.http.put('../rest/contactsParameters', { 'contactsFilling': this.contactsFilling, 'contactsParameters': this.contactsParameters })
            .subscribe(() => {
                this.notify.success(this.translate.instant('lang.parameterUpdated'));

            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    toggleFillingContact() {
        this.contactsFilling.enable === true ? this.contactsFilling.enable = false : this.contactsFilling.enable = true;
        this.onSubmit();
    }

    originalOrder = (a: KeyValue<string, any>, b: KeyValue<string, any>): number => 0;
}
