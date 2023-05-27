import { Component, ViewChild, OnInit, HostListener, AfterViewInit } from '@angular/core';
import { DomSanitizer } from '@angular/platform-browser';
import { MatIconRegistry } from '@angular/material/icon';
import { MAT_TOOLTIP_DEFAULT_OPTIONS, MatTooltipDefaultOptions } from '@angular/material/tooltip';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { MatSidenav } from '@angular/material/sidenav';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '@service/auth.service';
import { environment } from '../environments/environment';
import { TranslateService } from '@ngx-translate/core';
import { DateAdapter } from '@angular/material/core';
import { MatDialog } from '@angular/material/dialog';

/** Custom options the configure the tooltip's default show/hide delays. */
export const myCustomTooltipDefaults: MatTooltipDefaultOptions = {
    showDelay: 500,
    hideDelay: 0,
    touchendHideDelay: 0,
};

@Component({
    selector: 'app-root',
    templateUrl: 'app.component.html',
    viewProviders: [MatIconRegistry],
    providers: [
        AppService,
        { provide: MAT_TOOLTIP_DEFAULT_OPTIONS, useValue: myCustomTooltipDefaults }
    ],
})
export class AppComponent implements OnInit, AfterViewInit {

    @ViewChild('snavLeft', { static: false }) snavLeft: MatSidenav;

    debugMode: boolean = false;
    loading: boolean = true;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        iconReg: MatIconRegistry,
        sanitizer: DomSanitizer,
        public appService: AppService,
        public headerService: HeaderService,
        public authService: AuthService,
        private adapter: DateAdapter<any>,
        public dialog: MatDialog,
    ) {
        translate.setDefaultLang('fr');
        iconReg.addSvgIcon('maarchLogo', sanitizer.bypassSecurityTrustResourceUrl('../rest/images?image=onlyLogo'));
        iconReg.addSvgIcon('maarchLogoFull', sanitizer.bypassSecurityTrustResourceUrl('../rest/images?image=logo'));
        iconReg.addSvgIcon('maarchLogoWhite', sanitizer.bypassSecurityTrustResourceUrl('assets/logo_only_white.svg'));
        iconReg.addSvgIcon('maarchLogoWhiteFull', sanitizer.bypassSecurityTrustResourceUrl('assets/logo_white.svg'));
        iconReg.addSvgIcon('alfresco', sanitizer.bypassSecurityTrustResourceUrl('assets/alfresco.svg'));
        iconReg.addSvgIcon('multigest', sanitizer.bypassSecurityTrustResourceUrl('assets/multigest.svg'));
        if (!environment.production) {
            this.debugMode = true;
        }
    }

    @HostListener('window:resize', ['$event'])
    onResize() {
        this.appService.setScreenWidth(window.innerWidth);
    }

    async ngOnInit() {
        this.loading = false;
        this.headerService.hideSideBar = true;
        setTimeout(() => {
            this.headerService.sideNavLeft = this.snavLeft;
        }, 0);
        this.translate.get('lang.langISO').subscribe((res: string) => {
            this.adapter.setLocale(res);
        });
    }

    ngAfterViewInit(): void {
        this.appService.setScreenWidth(window.innerWidth);
    }
}
