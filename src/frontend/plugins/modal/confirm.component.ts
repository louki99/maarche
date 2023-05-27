import { Component, Inject } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { HeaderService } from '@service/header.service';
import { LocalStorageService } from '@service/local-storage.service';

@Component({
    templateUrl: 'confirm.component.html',
    styleUrls: ['confirm.component.scss']
})
export class ConfirmComponent {

    idModal: string = null;

    constructor(
        public translate: TranslateService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<ConfirmComponent>,
        public headerService: HeaderService,
        private localStorage: LocalStorageService
    ) {
        if (this.data.idModal !== undefined) {
            this.idModal = this.data.idModal;
        }

        if (this.data.msg === null) {
            this.data.msg = '';
        }

        if (this.data.buttonCancel === undefined) {
            this.data.buttonCancel = this.translate.instant('lang.cancel');
        }

        if (this.data.buttonValidate === undefined) {
            this.data.buttonValidate = this.translate.instant('lang.ok');
        }
    }

    hideModal() {
        if (this.idModal !== '') {
            this.localStorage.save(`modal_${this.idModal}_${this.headerService.user.id}`, true);
        } else {
            alert('No idModal provided!');
        }
    }
}
