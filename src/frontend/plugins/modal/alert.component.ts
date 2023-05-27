import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { AuthService } from '@service/auth.service';
import { FunctionsService } from '@service/functions.service';

@Component({
    templateUrl: 'alert.component.html',
    styleUrls: ['alert.component.scss']
})
export class AlertComponent implements OnInit {
    public authService: AuthService;
    constructor(
        public translate: TranslateService,
        public dialogRef: MatDialogRef<AlertComponent>,
        public functions: FunctionsService,
        @Inject(MAT_DIALOG_DATA) public data: any,
    ) {
        if (this.data.mode === null || this.data.mode === undefined) {
            this.data.mode = 'info';
        }
        this.data.mode = 'alert-message-' + this.data.mode;
        if (this.data.msg === null) {
            this.data.msg = '';
        }
    }

    ngOnInit(): void {
        if (this.data?.isCounter !== undefined) {
            let timeLeft: number = 10; // secondes
            this.data.msg = this.translate.instant('lang.inactivityWarning', {counter: timeLeft});
            const interval = setInterval(() => {
                if (timeLeft > 0) {
                    timeLeft--;
                    this.data.msg = this.translate.instant('lang.inactivityWarning', {counter: timeLeft});
                } else {
                    clearInterval(interval);
                }
            }, 1000);
        }
    }

    close() {
        this.dialogRef.close(this.data?.isCounter !== undefined ? 'resetTimer' : '');
    }
}
