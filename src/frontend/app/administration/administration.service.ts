import { Injectable, ChangeDetectorRef, OnInit, OnDestroy } from '@angular/core';
import { LocalStorageService } from '@service/local-storage.service';
import { HeaderService } from '@service/header.service';
import { FunctionsService } from '@service/functions.service';
import { MatTableDataSource } from '@angular/material/table';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort, Sort, MatSortable } from '@angular/material/sort';
import { of ,  merge } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { UntypedFormControl } from '@angular/forms';
import { catchError, startWith, tap } from 'rxjs/operators';

@Injectable()
export class AdministrationService {
    filters: any = {};
    defaultFilters: any = {
        admin_users: {
            sort: 'user_id',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_actions: {
            sort: 'id',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_baskets: {
            sort: 'basket_id',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_contacts_groups: {
            sort: 'label',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_listmodels: {
            sort: 'title',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_groups: {
            sort: 'group_desc',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_indexing_models: {
            sort: 'label',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_notif: {
            sort: 'notification_id',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_parameters: {
            sort: 'id',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_priorities: {
            sort: 'label',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_shippings: {
            sort: 'label',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_status: {
            sort: 'label_status',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_tag: {
            sort: 'label',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_templates: {
            sort: 'template_label',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_alfresco: {
            sort: 'label',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_contacts_list: {
            sort: 'lastname',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_regitered_mail_issuing_site: {
            sort: 'accountNumber',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_regitered_mail: {
            sort: 'rangeNumber',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_sso: {
            sort: 'label',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
        admin_attachments: {
            sort: 'label',
            sortDirection: 'asc',
            page: 0,
            field: ''
        },
    };
    dataSource: MatTableDataSource<any>;
    filterColumns: string[];
    searchTerm: UntypedFormControl = new UntypedFormControl('');
    currentAdminId: string = '';

    constructor(
        private notify: NotificationService,
        public headerService: HeaderService,
        public functionsService: FunctionsService,
        private localStorage: LocalStorageService,
    ) { }

    setAdminId(adminId: string) {
        this.currentAdminId = adminId;
    }

    setDataSource(adminId: string, data: any, sort: MatSort, paginator: MatPaginator, filterColumns: string[]) {
        this.currentAdminId = adminId;

        if (this.localStorage.get(`filtersAdmin_${this.headerService.user.id}`) !== null) {
            this.filters = JSON.parse(this.localStorage.get(`filtersAdmin_${this.headerService.user.id}`));
            if (this.filters[adminId] === undefined) {
                this.saveDefaultFilter();
            }
        } else {
            this.saveDefaultFilter();
        }
        this.searchTerm = new UntypedFormControl('');

        this.searchTerm.valueChanges
            .pipe(
                // debounceTime(300),
                // filter(value => value.length > 2),
                tap((filterValue: any) => {
                    this.setFilter('field', filterValue);
                    this.saveFilter(this.filters[this.currentAdminId]);
                    filterValue = filterValue.trim(); // Remove whitespace
                    filterValue = filterValue.toLowerCase(); // MatTableDataSource defaults to lowercase matches
                    setTimeout(() => {
                        this.dataSource.filter = filterValue;
                    }, 0);
                    this.dataSource.filterPredicate = (template, filter: string) => this.functionsService.filterUnSensitive(template, filter, this.filterColumns);
                }),
            ).subscribe();
        this.filterColumns = filterColumns;
        this.dataSource = new MatTableDataSource(data);

        this.dataSource.paginator = paginator;
        this.dataSource.sortingDataAccessor = this.functionsService.listSortingDataAccessor;

        // sort.active = this.getFilter('sort');
        // sort.direction = this.getFilter('sortDirection');
        paginator.pageIndex = this.getFilter('page');

        this.dataSource.sort = sort;

        // WORKAROUND TO SHOW ARROW DEFAULT FILTER
        const element: HTMLElement = document.getElementsByClassName('mat-column-' + this.getFilter('sort'))[0] as HTMLElement;
        if (document.getElementsByClassName('mat-column-' + this.getFilter('sort')).length > 0) {
            element.click();
        }
        if (this.getFilter('sortDirection') === 'desc') {
            element.click();
        }

        this.searchTerm.setValue(this.getFilter('field'));

        merge(sort.sortChange, paginator.page)
            .pipe(
                startWith({}),
                tap(() => {
                    this.saveFilter(
                        {
                            sort: sort.active,
                            sortDirection: sort.direction,
                            page: paginator.pageIndex,
                            field: this.getFilter('field')
                        }
                    );
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
    }

    saveDefaultFilter() {
        this.saveFilter(
            this.defaultFilters[this.currentAdminId]
        );
    }

    setFilter(idFilter: string, value: string) {
        this.filters[this.currentAdminId][idFilter] = value;
    }

    saveFilter(filter: any) {
        this.filters[this.currentAdminId] = filter;
        this.localStorage.save(`filtersAdmin_${this.headerService.user.id}`, JSON.stringify(this.filters));
    }

    getFilterField() {
        return this.searchTerm;
    }

    getDataSource() {
        return this.dataSource;
    }

    getFilter(idFilter: string = null) {
        if (!this.functionsService.empty(this.filters[this.currentAdminId])) {
            if (!this.functionsService.empty(idFilter)) {
                return !this.functionsService.empty(this.filters[this.currentAdminId][idFilter]) ? this.filters[this.currentAdminId][idFilter] : '';
            } else {
                return !this.functionsService.empty(this.filters[this.currentAdminId]) ? this.filters[this.currentAdminId] : '';
            }
        } else {
            return null;
        }
    }
}
