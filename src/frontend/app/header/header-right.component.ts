import { Component, OnInit, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { HeaderService } from '@service/header.service';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { MatInput } from '@angular/material/input';
import { IndexingGroupModalComponent } from '../menu/menu-shortcut.component';
import { Router } from '@angular/router';
import { AppService } from '@service/app.service';
import { PrivilegeService } from '@service/privileges.service';
import { FunctionsService } from '@service/functions.service';
import { AuthService } from '@service/auth.service';
import { RegisteredMailImportComponent } from '@appRoot/registeredMail/import/registered-mail-import.component';
import { AboutUsComponent } from '@appRoot/about-us.component';
import { LocalStorageService } from '@service/local-storage.service';


@Component({
    selector: 'app-header-right',
    styleUrls: ['header-right.component.scss'],
    templateUrl: 'header-right.component.html',
})
export class HeaderRightComponent implements OnInit {

    @ViewChild('searchInput', { static: false }) searchInput: MatInput;

    dialogRef: MatDialogRef<any>;
    config: any = {};
    menus: any = [];
    searchTarget: string = '';

    hideSearch: boolean = true;

    quickSearchTargets: any[] = [
        {
            id: 'searchTerm',
            label: this.translate.instant('lang.defaultQuickSearch'),
            desc: this.translate.instant('lang.quickSearchTarget'),
            icon: 'fas fa-inbox fa-2x',
        },
        {
            id: 'recipients',
            label: this.translate.instant('lang.recipient'),
            desc: this.translate.instant('lang.searchByRecipient'),
            icon: 'fas fa-user fa-2x',
        },
        {
            id: 'senders',
            label: this.translate.instant('lang.sender'),
            desc: this.translate.instant('lang.searchBySender'),
            icon: 'fas fa-address-book fa-2x',
        }
    ];

    selectedQuickSearchTarget: string = 'searchTerm';

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public router: Router,
        public dialog: MatDialog,
        public authService: AuthService,
        public appService: AppService,
        public headerService: HeaderService,
        public functions: FunctionsService,
        public privilegeService: PrivilegeService,
        private localStorage: LocalStorageService
    ) { }

    ngOnInit(): void {
        this.menus = this.privilegeService.getCurrentUserMenus();
        if (!this.functions.empty(this.localStorage.get('quickSearchTarget'))) {
            this.selectedQuickSearchTarget = this.localStorage.get('quickSearchTarget');
        }
    }

    gotToMenu(shortcut: any) {
        if (shortcut.id === 'indexing' && shortcut.groups.length > 1) {
            this.config = { panelClass: 'maarch-modal', data: { indexingGroups: shortcut.groups, link: shortcut.route } };
            this.dialogRef = this.dialog.open(IndexingGroupModalComponent, this.config);
        } else {
            const component = shortcut.route.split('__');

            if (component.length === 2) {
                if (component[0] === 'RegisteredMailImportComponent') {
                    this.dialog.open(RegisteredMailImportComponent, {
                        disableClose: true,
                        width: '99vw',
                        maxWidth: '99vw',
                        panelClass: 'maarch-full-height-modal'
                    });
                }
            } else {
                this.router.navigate([shortcut.route]);
            }
        }
    }

    showSearchInput() {
        this.hideSearch = !this.hideSearch;
        setTimeout(() => {
            this.searchInput.focus();
        }, 200);
    }

    hideSearchBar() {
        if (this.privilegeService.getCurrentUserMenus().find((privilege: any) => privilege.id === 'adv_search_mlb') === undefined) {
            return false;
        } else {
            return this.router.url.split('?')[0] !== '/search';
        }
    }

    showLogout() {
        return this.authService.canLogOut();
    }

    goTo() {
        this.router.navigate(['/search'], { queryParams: { target: this.selectedQuickSearchTarget, value: this.searchTarget } });
    }

    openAboutModal() {
        this.dialog.open(AboutUsComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: false });
    }

    setTarget(id: string) {
        this.selectedQuickSearchTarget = id;
        this.localStorage.save('quickSearchTarget', this.selectedQuickSearchTarget);
    }

    getTargetDesc(): string {
        return this.quickSearchTargets.find((item: any) => item.id === this.selectedQuickSearchTarget).desc;
    }

    getTargetIcon(): string {
        return this.quickSearchTargets.find((item: any) => item.id === this.selectedQuickSearchTarget).icon;
    }
}
