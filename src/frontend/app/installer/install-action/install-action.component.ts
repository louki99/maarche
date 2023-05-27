import { Component, OnInit, Inject, AfterViewInit } from '@angular/core';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { of } from 'rxjs';
import { InstallerService } from '../installer.service';
import { NotificationService } from '@service/notification/notification.service';
import { catchError, tap } from 'rxjs/operators';
import { AuthService } from '@service/auth.service';

@Component({
    selector: 'app-install-action',
    templateUrl: './install-action.component.html',
    styleUrls: ['./install-action.component.scss']
})
export class InstallActionComponent implements OnInit, AfterViewInit {
    steps: any[] = [];
    customId: string = '';

    // Workaround for angular component issue #13870
    disableAnimation = true;

    constructor(
        public translate: TranslateService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<InstallActionComponent>,
        public http: HttpClient,
        private installerService: InstallerService,
        private notify: NotificationService,
        private authService: AuthService,
    ) { }

    async ngOnInit(): Promise<void> {
        this.initSteps();

    }

    ngAfterViewInit(): void {
        setTimeout(() => this.disableAnimation = false);
    }

    async launchInstall() {
        let res: any;
        for (let index = 0; index < this.data.length; index++) {
            this.steps[index].state = 'inProgress';
            res = await this.doStep(index);
            if (!res) {
                break;
            }
        }
    }

    initSteps() {
        this.data.forEach((step: any, index: number) => {
            if (index === 0) {
                this.customId = step.body.customId;
            } else {
                step.body.customId = this.customId;
            }
            this.steps.push(
                {
                    idStep : step.idStep,
                    label: step.description,
                    state: '',
                    msgErr: '',
                }
            );
        });
    }

    doStep(index: number) {
        return new Promise((resolve, reject) => {
            if (this.installerService.isStepAlreadyLaunched(this.data[index].idStep)) {
                this.steps[index].state = 'OK';
                resolve(true);
            } else {
                this.http[this.data[index].route.method.toLowerCase()](this.data[index].route.url, this.data[index].body).pipe(
                    tap((data: any) => {
                        this.steps[index].state = 'OK';
                        this.installerService.setStep(this.steps[index]);
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.steps[index].state = 'KO';
                        if (err.error.lang !== undefined) {
                            this.steps[index].msgErr = this.translate.instant('lang.' + err.error.lang);
                        } else {
                            this.steps[index].msgErr = err.error.errors;
                        }
                        resolve(false);
                        return of(false);
                    })
                ).subscribe();
            }
        });
    }

    isInstallBegin() {
        return this.steps.filter(step => step.state === '').length !== this.steps.length;
    }

    isInstallComplete() {
        return this.steps.filter(step => step.state === '').length === 0;
    }

    isInstallError() {
        return this.steps.filter(step => step.state === 'KO').length > 0;
    }

    goToInstance() {
        this.http.request('DELETE', '../rest/installer/lock', { body: { customId: this.customId } }).pipe(
            tap((res: any) => {
                this.authService.noInstall = false;
                window.location.href = res.url;
                this.dialogRef.close('ok');
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
