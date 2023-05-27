import { Component, Inject, ViewChild, Renderer2, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialog } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { PrivilegeService } from '@service/privileges.service';
import { HeaderService } from '@service/header.service';
import { MatSidenav } from '@angular/material/sidenav';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { catchError, exhaustMap, filter } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationSpec } from 'tinymce';
import { NotificationService } from '@service/notification/notification.service';


declare let $: any;

@Component({
    templateUrl: 'contact-modal.component.html',
    styleUrls: ['contact-modal.component.scss'],
})
export class ContactModalComponent implements OnInit{

    @ViewChild('drawer', { static: true }) drawer: MatSidenav;

    creationMode: boolean = true;
    canUpdate: boolean = false;
    contact: any = null;
    mode: 'update' | 'read' = 'read';
    loadedDocument: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private privilegeService: PrivilegeService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<ContactModalComponent>,
        public headerService: HeaderService,
        public dialog: MatDialog,
        public notify: NotificationService,
        private renderer: Renderer2) {
    }

    ngOnInit(): void {
        if (this.data.contactId !== null) {
            this.contact = {
                id: this.data.contactId,
                type: this.data.contactType
            };
            this.creationMode = false;
        } else {
            this.creationMode = true;
            this.mode = 'update';
            if (this.mode === 'update') {
                $('.maarch-modal').css({ 'height': '99vh' });
                $('.maarch-modal').css({ 'width': '99vw' });
            }
            if (this.headerService.getLastLoadedFile() !== null) {
                this.drawer.toggle();
                setTimeout(() => {
                    this.loadedDocument = true;
                }, 200);
            }
        }
        this.canUpdate = this.privilegeService.hasCurrentUserPrivilege('update_contacts');
    }

    switchMode() {
        this.mode = this.mode === 'read' ? 'update' : 'read';
        if (this.mode === 'update') {
            $('.maarch-modal').css({ 'height': '99vh' });
            $('.maarch-modal').css({ 'width': '99vw' });
        }

        if (this.headerService.getLastLoadedFile() !== null) {
            this.drawer.toggle();
            setTimeout(() => {
                this.loadedDocument = true;
            }, 200);
        }
    }

    linkContact(contactId: any) {
        const dialogRef = this.dialog.open(ConfirmComponent,
            { panelClass: 'maarch-modal',
                autoFocus: false, disableClose: true,
                data: {
                    title: this.translate.instant('lang.linkContact'),
                    msg: this.translate.instant('lang.goToContact')
                }
            });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(async () => this.dialogRef.close(contactId)),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
