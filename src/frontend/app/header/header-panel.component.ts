import { Component, OnInit, Input } from '@angular/core';
import { Location } from '@angular/common';
import { TranslateService } from '@ngx-translate/core';
import { HeaderService } from '@service/header.service';
import { MatDialogRef } from '@angular/material/dialog';
import { MatSidenav } from '@angular/material/sidenav';
import { AppService } from '@service/app.service';
import { Router } from '@angular/router';

@Component({
    selector: 'app-header-panel',
    styleUrls: ['header-panel.component.scss'],
    templateUrl: 'header-panel.component.html',
})
export class HeaderPanelComponent implements OnInit {

    @Input() navButton: any = null;
    @Input() snavLeft: MatSidenav;

    dialogRef: MatDialogRef<any>;
    config: any = {};

    constructor(
        public translate: TranslateService,
        public headerService: HeaderService,
        public appService: AppService,
        private router: Router,
        private _location: Location
    ) { }

    ngOnInit(): void { }

    goTo() {
        if (this.headerService.sideBarButton.route === '__GOBACK') {
            this._location.back();
        } else {
            this.router.navigate([this.headerService.sideBarButton.route]);
        }

    }

    goToHome() {
        this.router.navigate(['/home']);
    }
}
