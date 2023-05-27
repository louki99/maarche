import { Component, OnInit, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { MatPaginator } from '@angular/material/paginator';
import { MatSidenav } from '@angular/material/sidenav';
import { MatSort } from '@angular/material/sort';
import { MatTableDataSource } from '@angular/material/table';
import { AppService } from '@service/app.service';
import { PrivilegeService } from '@service/privileges.service';
import { tap, catchError, exhaustMap, map, finalize, filter } from 'rxjs/operators';
import { MatDialog } from '@angular/material/dialog';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { of } from 'rxjs';
import { AuthService } from '@service/auth.service';

@Component({
    templateUrl: 'group-administration.component.html',
    styleUrls: ['group-administration.component.scss']
})
export class GroupAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('paginatorBaskets', { static: false }) paginatorBaskets: MatPaginator;
    @ViewChild('sortBaskets', { static: false }) sortBaskets: MatSort;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild('sortUsers', { static: false }) sortUsers: MatSort;

    loading: boolean = false;
    paramsLoading: boolean = false;

    group: any = {
        security: {}
    };
    creationMode: boolean;
    menus: any = {};

    usersDisplayedColumns = ['firstname', 'lastname'];
    basketsDisplayedColumns = ['basket_name', 'basket_desc'];
    usersDataSource: any;
    basketsDataSource: any;

    unitPrivileges: any[] = [];

    administrationPrivileges: any[] = [];

    authorizedGroupsUserParams: any[] = [];
    panelMode = 'keywordInfos';

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public privilegeService: PrivilegeService,
        public authService: AuthService,
        private dialog: MatDialog
    ) { }

    ngOnInit(): void {
        this.loading = true;

        this.route.params.subscribe(params => {
            if (typeof params['id'] === 'undefined') {

                this.headerService.setHeader(this.translate.instant('lang.groupCreation'));

                this.creationMode = true;
                this.loading = false;
            } else {
                this.creationMode = false;
                this.http.get('../rest/groups/' + params['id'] + '/details')
                    .subscribe((data: any) => {
                        this.group = data['group'];

                        this.administrationPrivileges = this.privilegeService.getAdministrations();

                        this.administrationPrivileges = this.administrationPrivileges.map(admin => ({
                            ...admin,
                            checked: this.group.privileges.indexOf(admin.id) > -1
                        }));

                        this.privilegeService.getUnitsPrivileges().forEach(element => {
                            let services: any[] = this.privilegeService.getPrivilegesByUnit(element);

                            if (element === 'diffusionList') {
                                services = [
                                    {
                                        'id': 'indexing_diffList',
                                        'label': this.translate.instant('lang.diffListPrivilegeMsgIndexing'),
                                        'current': this.group.privileges.filter((priv: any) => ['update_diffusion_indexing', 'update_diffusion_except_recipient_indexing'].indexOf(priv) > -1)[0] !== undefined ? this.group.privileges.filter((priv: any) => ['update_diffusion_indexing', 'update_diffusion_except_recipient_indexing'].indexOf(priv) > -1)[0] : '',
                                        'services': this.privilegeService.getPrivileges(['update_diffusion_indexing', 'update_diffusion_except_recipient_indexing'])
                                    },
                                    {
                                        'id': 'process_diffList',
                                        'label': this.translate.instant('lang.diffListPrivilegeMsgProcess'),
                                        'current': this.group.privileges.filter((priv: any) => ['update_diffusion_process', 'update_diffusion_except_recipient_process'].indexOf(priv) > -1)[0] !== undefined ? this.group.privileges.filter((priv: any) => ['update_diffusion_process', 'update_diffusion_except_recipient_process'].indexOf(priv) > -1)[0] : '',
                                        'services': this.privilegeService.getPrivileges(['update_diffusion_process', 'update_diffusion_except_recipient_process'])
                                    },
                                    {
                                        'id': 'details_diffList',
                                        'label': this.translate.instant('lang.diffListPrivilegeMsgDetails'),
                                        'current': this.group.privileges.filter((priv: any) => ['update_diffusion_details', 'update_diffusion_except_recipient_details'].indexOf(priv) > -1)[0] !== undefined ? this.group.privileges.filter((priv: any) => ['update_diffusion_details', 'update_diffusion_except_recipient_details'].indexOf(priv) > -1)[0] : '',
                                        'services': this.privilegeService.getPrivileges(['update_diffusion_details', 'update_diffusion_except_recipient_details'])
                                    }
                                ];
                            } else if (element === 'confidentialityAndSecurity') {
                                let priv = '';
                                if (this.group.privileges.filter((privGroup: any) => privGroup === 'manage_personal_data')[0]) {
                                    priv = 'manage_personal_data';
                                } else if (this.group.privileges.filter((privGroup: any) => privGroup === 'view_personal_data')[0]) {
                                    priv = 'view_personal_data';
                                }
                                services = [
                                    {
                                        'id': 'confidentialityAndSecurity_personal_data',
                                        'label': this.translate.instant('lang.personalDataMsg'),
                                        'current': priv,
                                        'services': this.privilegeService.getPrivileges(['view_personal_data', 'manage_personal_data'])
                                    }
                                ];
                            }

                            this.unitPrivileges.push({
                                id: element,
                                label: this.translate.instant('lang.' + element),
                                services: services
                            });
                        });
                        this.headerService.setHeader(this.translate.instant('lang.groupModification'), this.group['group_desc']);

                        this.loading = false;
                        setTimeout(() => {
                            this.usersDataSource = new MatTableDataSource(this.group.users);
                            this.usersDataSource.paginator = this.paginator;
                            this.usersDataSource.sort = this.sortUsers;
                            this.basketsDataSource = new MatTableDataSource(this.group.baskets);
                            this.basketsDataSource.paginator = this.paginatorBaskets;
                            this.basketsDataSource.sort = this.sortBaskets;
                        }, 0);

                    }, (err) => {
                        this.notify.handleErrors(err);
                    });
            }
        });
    }

    changeDifflistPrivilege(ev: any, mode: string) {
        if (mode === 'indexing_diffList') {
            if (ev.value === 'update_diffusion_indexing') {
                this.manageServices(['update_diffusion_indexing', 'update_diffusion_except_recipient_indexing']);
            } else if (ev.value === 'update_diffusion_except_recipient_indexing') {
                this.manageServices(['update_diffusion_except_recipient_indexing', 'update_diffusion_indexing']);
            } else {
                this.manageServices(['update_diffusion_indexing', 'update_diffusion_except_recipient_indexing'], 'deleteAll');
            }
        } else if (mode === 'process_diffList') {
            if (ev.value === 'update_diffusion_process') {
                this.manageServices(['update_diffusion_process', 'update_diffusion_except_recipient_process']);
            } else if (ev.value === 'update_diffusion_except_recipient_process') {
                this.manageServices(['update_diffusion_except_recipient_process', 'update_diffusion_process']);
            } else {
                this.manageServices(['update_diffusion_process', 'update_diffusion_except_recipient_process'], 'deleteAll');
            }
        } else {
            if (ev.value === 'update_diffusion_details') {
                this.manageServices(['update_diffusion_details', 'update_diffusion_except_recipient_details']);
            } else if (ev.value === 'update_diffusion_except_recipient_details') {
                this.manageServices(['update_diffusion_except_recipient_details', 'update_diffusion_details']);
            } else {
                this.manageServices(['update_diffusion_details', 'update_diffusion_except_recipient_details'], 'deleteAll');

            }
        }

    }

    manageServices(servicesId: any[], mode: string = null) {
        if (mode !== 'deleteAll') {
            this.http.post(`../rest/groups/${this.group.id}/privileges/${servicesId[0]}`, {}).pipe(
                tap(() => {
                    this.group.privileges.push(servicesId[0]);
                }),
                exhaustMap(() => this.http.delete(`../rest/groups/${this.group.id}/privileges/${servicesId[1]}`)),
                tap(() => {
                    this.group.privileges.splice(this.group.privileges.indexOf(servicesId[1]), 1);
                    this.headerService.resfreshCurrentUser();
                    this.notify.success(this.translate.instant('lang.groupServicesUpdated'));
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.http.delete(`../rest/groups/${this.group.id}/privileges/${servicesId[0]}`).pipe(
                tap(() => {
                    this.group.privileges.splice(this.group.privileges.indexOf(servicesId[0]), 1);
                }),
                exhaustMap(() => this.http.delete(`../rest/groups/${this.group.id}/privileges/${servicesId[1]}`)),
                tap(() => {
                    this.group.privileges.splice(this.group.privileges.indexOf(servicesId[1]), 1);
                    this.headerService.resfreshCurrentUser();
                    this.notify.success(this.translate.instant('lang.groupServicesUpdated'));
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    changePersonalDataPrivilege(ev: any) {

        if (ev.value === 'view_personal_data') {

            this.manageServices(['view_personal_data', 'manage_personal_data']);

        } else if (ev.value === 'manage_personal_data') {
            this.http.post(`../rest/groups/${this.group.id}/privileges/view_personal_data`, {}).pipe(
                tap(() => {
                    this.group.privileges.push('view_personal_data');
                }),
                exhaustMap(() => this.http.post(`../rest/groups/${this.group.id}/privileges/manage_personal_data`, {})),
                tap(() => {
                    this.group.privileges.splice(this.group.privileges.indexOf('manage_personal_data'), 1);
                    this.headerService.resfreshCurrentUser();
                    this.notify.success(this.translate.instant('lang.groupServicesUpdated'));
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();

        } else {
            this.manageServices(['view_personal_data', 'manage_personal_data'], 'deleteAll');
        }

    }

    async resfreshShortcut() {
        await this.headerService.resfreshCurrentUser();
        this.privilegeService.resfreshUserShortcuts();
    }

    getCurrentPrivListDiff(serviceId: string) {
        if (this.group.privileges.indexOf(serviceId) > -1) {
            return true;
        } else {
            return false;
        }
    }

    onSubmit() {
        if (this.creationMode) {
            this.http.post('../rest/groups', this.group)
                .subscribe((data: any) => {
                    this.notify.success(this.translate.instant('lang.groupAdded'));
                    this.router.navigate(['/administration/groups/' + data.group]);
                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        } else {
            this.http.put('../rest/groups/' + this.group['id'], { 'description': this.group['group_desc'], 'security': this.group['security'] })
                .subscribe(() => {
                    this.notify.success(this.translate.instant('lang.groupUpdated'));
                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        }
    }

    toggleService(ev: any, service: any) {
        if (ev.checked) {
            if (service.id === 'admin_groups') {
                const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.confirmAction'), msg: this.translate.instant('lang.enableGroupMsg') } });

                dialogRef.afterClosed().pipe(
                    tap((data: string) => {
                        if (data !== 'ok') {
                            service.checked = false;
                        }
                    }),
                    filter((data: string) => data === 'ok'),
                    tap(() => {
                        this.addService(service);
                    }),
                    catchError((err: any) => {
                        this.notify.handleErrors(err);
                        return of(false);
                    })
                ).subscribe();
            } else {
                this.addService(service);
            }

        } else {
            this.sidenavRight.close();
            this.removeService(service);
        }
    }

    addService(service: any) {
        this.http.post(`../rest/groups/${this.group.id}/privileges/${service.id}`, {}).pipe(
            tap(() => {
                this.group.privileges.push(service.id);
                this.headerService.resfreshCurrentUser();
                this.notify.success(this.translate.instant('lang.groupServicesUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    removeService(service: any) {
        this.http.delete(`../rest/groups/${this.group.id}/privileges/${service.id}`).pipe(
            tap(() => {
                this.group.privileges.splice(this.group.privileges.indexOf(service.id), 1);
                this.headerService.resfreshCurrentUser();
                this.notify.success(this.translate.instant('lang.groupServicesUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    linkUser(newUser: any) {
        const groupReq = {
            'groupId': this.group.group_id,
            'role': this.group.role
        };
        this.http.post('../rest/users/' + newUser.id + '/groups', groupReq)
            .subscribe(() => {
                const displayName = newUser.idToDisplay.split(' ');
                const user = {
                    id: newUser.id,
                    user_id: newUser.otherInfo,
                    firstname: displayName[0],
                    lastname: displayName[1],
                    allowed: true
                };
                this.group.users.push(user);
                this.usersDataSource = new MatTableDataSource(this.group.users);
                this.usersDataSource.paginator = this.paginator;
                this.usersDataSource.sort = this.sortUsers;
                this.notify.success(this.translate.instant('lang.userAdded'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    openUserParams(id: string) {
        this.sidenavRight.toggle();
        if (!this.sidenavRight.opened) {
            this.panelMode = '';
        } else {
            this.panelMode = id;
            this.paramsLoading = true;
            this.http.get('../rest/groups').pipe(
                map((data: any) => {
                    data.groups = data.groups.map((group: any) => ({
                        id: group.id,
                        label: group.group_desc
                    }));
                    return data;
                }),
                tap((data: any) => {
                    this.authorizedGroupsUserParams = data.groups;
                }),
                exhaustMap(() => this.http.get(`../rest/groups/${this.group.id}/privileges/${this.panelMode}/parameters?parameter=groups`)),
                tap((data: any) => {
                    const allowedGroups: any[] = data;
                    this.authorizedGroupsUserParams.forEach(group => {
                        group.checked = allowedGroups.indexOf(group.id) > -1;
                    });
                }),
                finalize(() => this.paramsLoading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    updatePrivilegeParams(paramList: any) {
        let obj = {};
        if (this.panelMode === 'admin_users') {
            obj = {
                groups: paramList.map((param: any) => param.value)
            };
        }
        this.http.put(`../rest/groups/${this.group.id}/privileges/${this.panelMode}/parameters`, { parameters: obj }).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.parameterUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    goToUserAdmin(user: any) {
        if (user.allowed) {
            this.router.navigate(['/administration/users/' + user.id]);
        }
    }

    applyFilter(filterValue: string) {
        filterValue = filterValue.trim();
        filterValue = filterValue.toLowerCase();
        this.usersDataSource.filter = filterValue;
    }
    applyBasketsFilter(filterValue: string) {
        filterValue = filterValue.trim();
        filterValue = filterValue.toLowerCase();
        this.basketsDataSource.filter = filterValue;
    }
}
