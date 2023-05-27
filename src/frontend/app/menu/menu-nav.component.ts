import { Component, OnInit } from '@angular/core';
import { Location } from '@angular/common';
import { Router, ActivatedRoute } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';

@Component({
    selector: 'app-menu-nav',
    templateUrl: 'menuNav.component.html',
})
export class MenuNavComponent implements OnInit {


    router: any;
    user: any = {};

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private _router: Router,
        private activatedRoute: ActivatedRoute,
        private _location: Location
    ) {
        this.router = _router;
    }

    ngOnInit(): void { }

    backClicked() {
        // this.router.navigate(['../'],{ relativeTo: this.activatedRoute });
        this._location.back();
    }
}
