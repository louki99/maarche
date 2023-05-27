import { Component, OnInit, EventEmitter, Output } from '@angular/core';
import { UntypedFormBuilder, UntypedFormGroup, Validators, ValidatorFn } from '@angular/forms';
import { NotificationService } from '@service/notification/notification.service';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { of } from 'rxjs';
import { InstallerService } from '../installer.service';
import { catchError, tap } from 'rxjs/operators';

@Component({
    selector: 'app-docservers',
    templateUrl: './docservers.component.html',
    styleUrls: ['./docservers.component.scss']
})
export class DocserversComponent implements OnInit {

    @Output() nextStep = new EventEmitter<string>();

    stepFormGroup: UntypedFormGroup;

    constructor(
        public translate: TranslateService,
        private _formBuilder: UntypedFormBuilder,
        private notify: NotificationService,
        public http: HttpClient,
        private installerService: InstallerService
    ) {
        const valPath: ValidatorFn[] = [Validators.pattern(/^[^\'\<\>\|\*\:\?]+$/), Validators.required];

        this.stepFormGroup = this._formBuilder.group({
            docserversPath: ['/opt/maarch/docservers/', valPath],
            stateStep: ['', Validators.required],
        });

        this.stepFormGroup.controls['docserversPath'].valueChanges.pipe(
            tap(() => this.stepFormGroup.controls['stateStep'].setValue(''))
        ).subscribe();
    }

    ngOnInit(): void {
    }


    isValidStep() {
        if (this.installerService.isStepAlreadyLaunched('docserver')) {
            return true;
        } else {
            return this.stepFormGroup === undefined ? false : this.stepFormGroup.valid;
        }
    }

    initStep() {
        if (this.installerService.isStepAlreadyLaunched('docserver')) {
            this.stepFormGroup.disable();
        }
    }

    getFormGroup() {
        return this.installerService.isStepAlreadyLaunched('docserver') ? true : this.stepFormGroup;
    }

    checkAvailability() {
        const info = {
            path: this.stepFormGroup.controls['docserversPath'].value,
        };

        this.http.get('../rest/installer/docservers', { params: info }).pipe(
            tap((data: any) => {
                this.notify.success(this.translate.instant('lang.rightInformations'));
                this.stepFormGroup.controls['stateStep'].setValue('success');
                this.nextStep.emit();
            }),
            catchError((err: any) => {
                this.notify.error(this.translate.instant('lang.pathUnreacheable'));
                this.stepFormGroup.controls['stateStep'].setValue('');
                return of(false);
            })
        ).subscribe();
    }

    getInfoToInstall(): any[] {
        return [{
            idStep: 'docserver',
            body: {
                path: this.stepFormGroup.controls['docserversPath'].value,
            },
            route: {
                method: 'POST',
                url: '../rest/installer/docservers'
            },
            description: this.translate.instant('lang.stepDocserversActionDesc'),
            installPriority: 3
        }];
    }

}
