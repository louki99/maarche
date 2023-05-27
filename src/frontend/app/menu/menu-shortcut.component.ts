import { Component, OnInit, Inject } from '@angular/core';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialog } from '@angular/material/dialog';
import { AppService } from '@service/app.service';
import { PrivilegeService } from '@service/privileges.service';
import { HeaderService } from '@service/header.service';

@Component({
    selector: 'app-menu-shortcut',
    styleUrls: ['menu-shortcut.component.scss'],
    templateUrl: 'menu-shortcut.component.html',
})
export class MenuShortcutComponent implements OnInit {

    router: any;
    dialogRef: MatDialogRef<any>;
    config: any = {};
    speedDialFabButtons: any = [];
    speedDialFabColumnDirection = 'column';
    shortcuts: any;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private _router: Router,
        public dialog: MatDialog,
        public appService: AppService,
        public privilegeService: PrivilegeService,
        public headerService: HeaderService
    ) {
        this.router = _router;
    }

    ngOnInit(): void { }


    onSpeedDialFabClicked(group: any, shortcut: any) {
        this.router.navigate(['/indexing/' + group.id]);
    }

    gotToMenu(shortcut: any) {
        if (shortcut.id === 'indexing') {
            this.router.navigate([shortcut.route + '/' + shortcut.groups[0].id]);
        } else {
            this.router.navigate([shortcut.route]);
        }
    }
}
@Component({
    templateUrl: 'indexing-group-modal.component.html',
    styles: ['.mat-dialog-content{max-height: 65vh;width:600px;}']
})
export class IndexingGroupModalComponent {

    constructor(
        public http: HttpClient,
        private router: Router,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<IndexingGroupModalComponent>) {

    }

    goTo(group: any) {
        this.router.navigate(['/indexing/' + group.id]);
        this.dialogRef.close();
    }
}
