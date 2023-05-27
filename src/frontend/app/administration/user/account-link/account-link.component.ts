import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { ExternalSignatoryBookManagerService } from '@service/externalSignatoryBook/external-signatory-book-manager.service';
import { FunctionsService } from '@service/functions.service';
import { AuthService } from '@service/auth.service';

@Component({
    templateUrl: 'account-link.component.html',
    styleUrls: ['account-link.component.scss'],
    providers: [ExternalSignatoryBookManagerService]
})
export class AccountLinkComponent implements OnInit {

    externalUser: any = {
        inExternalSignatoryBook: false,
        login: '',
        firstname: '',
        lastname: '',
        email: '',
        picture: ''
    };

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public externalSignatoryBook: ExternalSignatoryBookManagerService,
        public functions: FunctionsService,
        public authService: AuthService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<AccountLinkComponent>,
        private notify: NotificationService
    ) {
    }

    async ngOnInit(): Promise<void> {
        const dataUsers: any = await this.externalSignatoryBook.getAutocompleteUsersDatas(this.data);
        if (!this.functions.empty(dataUsers)) {
            if (dataUsers.length > 0) {
                this.externalUser = dataUsers[0];
                this.externalUser.inExternalSignatoryBook = true;
                this.externalUser.picture = await this.externalSignatoryBook.getUserAvatar(this.externalUser.id);
            } else {
                this.externalUser.inExternalSignatoryBook = false;
                this.externalUser = this.data.user;
                this.externalUser.login = this.data.user.user_id;
                this.externalUser.email = this.data.user.mail;
            }
        } else if (dataUsers === null) {
            this.dialogRef.close('');
        }
    }

    async selectUser(user: any) {
        this.externalUser = user;
        this.externalUser.picture = await this.externalSignatoryBook.getUserAvatar(this.externalUser.id);
        this.externalUser.inExternalSignatoryBook = true;
    }

    removeItem() {
        this.externalUser.inExternalSignatoryBook = false;
        this.externalUser = this.data.user;
        this.externalUser.login = this.data.user.user_id;
        this.externalUser.email = this.data.user.mail;
    }

    getRouteDatas(): string[] {
        return [`${this.externalSignatoryBook.getAutocompleteUsersRoute()}?excludeAlreadyConnected=true`];
    }

    getUserFullName(externalUser: any): string {
        return !this.functions.empty(externalUser.idToDisplay) ? externalUser.idToDisplay : `${externalUser.firstname} ${externalUser.lastname}`;
    }

    getLabelPlaceHolder(): string {
        return `${this.translate.instant('lang.searchUserInExternalSignatoryBook')} ${this.translate.instant('lang.' + this.authService.externalSignatoryBook?.id)}`;
    }
}
