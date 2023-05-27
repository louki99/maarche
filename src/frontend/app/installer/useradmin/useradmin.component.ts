import { Component, OnInit, EventEmitter, Output } from '@angular/core';
import { UntypedFormGroup, UntypedFormBuilder, Validators, ValidatorFn } from '@angular/forms';
import { NotificationService } from '@service/notification/notification.service';
import { TranslateService } from '@ngx-translate/core';
import { InstallerService } from '../installer.service';
import { StepAction } from '../types';
import { tap } from 'rxjs/operators';

@Component({
    selector: 'app-useradmin',
    templateUrl: './useradmin.component.html',
    styleUrls: ['./useradmin.component.scss']
})
export class UseradminComponent implements OnInit {

    @Output() tiggerInstall = new EventEmitter<string>();

    stepFormGroup: UntypedFormGroup;

    hide: boolean = true;

    constructor(
        public translate: TranslateService,
        private _formBuilder: UntypedFormBuilder,
        private notify: NotificationService,
        private installerService: InstallerService
    ) {

        const valLogin: ValidatorFn[] = [Validators.pattern(/^[\w.@-]*$/), Validators.required];
        const valEmail: ValidatorFn[] = [Validators.pattern(/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/), Validators.required];

        this.stepFormGroup = this._formBuilder.group({
            login: ['superadmin', valLogin],
            firstname: ['Admin', Validators.required],
            lastname: ['SUPER', Validators.required],
            password: ['', Validators.required],
            passwordConfirm: ['', Validators.required],
            email: ['yourEmail@domain.com', valEmail],
        });
    }

    ngOnInit(): void {
        this.stepFormGroup.controls['password'].valueChanges.pipe(
            tap((data) => {
                if (data !== this.stepFormGroup.controls['passwordConfirm'].value) {
                    this.stepFormGroup.controls['password'].setErrors({ 'incorrect': true });
                    this.stepFormGroup.controls['passwordConfirm'].setErrors({ 'incorrect': true });
                    this.stepFormGroup.controls['passwordConfirm'].markAsTouched();
                } else {
                    this.stepFormGroup.controls['password'].setErrors(null);
                    this.stepFormGroup.controls['passwordConfirm'].setErrors(null);
                }
            })
        ).subscribe();
        this.stepFormGroup.controls['passwordConfirm'].valueChanges.pipe(
            tap((data) => {
                if (data !== this.stepFormGroup.controls['password'].value) {
                    this.stepFormGroup.controls['password'].setErrors({ 'incorrect': true });
                    this.stepFormGroup.controls['password'].markAsTouched();
                    this.stepFormGroup.controls['passwordConfirm'].setErrors({ 'incorrect': true });
                } else {
                    this.stepFormGroup.controls['password'].setErrors(null);
                    this.stepFormGroup.controls['passwordConfirm'].setErrors(null);
                }
            })
        ).subscribe();
    }

    initStep() {
        if (this.installerService.isStepAlreadyLaunched('userAdmin')) {
            this.stepFormGroup.disable();
        }
    }

    isValidStep() {
        return this.stepFormGroup === undefined ? false : this.stepFormGroup.valid || this.installerService.isStepAlreadyLaunched('userAdmin');
    }

    getFormGroup() {
        return this.installerService.isStepAlreadyLaunched('userAdmin') ? true : this.stepFormGroup;
    }

    getInfoToInstall(): StepAction[] {
        return [{
            idStep: 'userAdmin',
            body: {
                login: this.stepFormGroup.controls['login'].value,
                firstname: this.stepFormGroup.controls['firstname'].value,
                lastname: this.stepFormGroup.controls['lastname'].value,
                password: this.stepFormGroup.controls['password'].value,
                email: this.stepFormGroup.controls['email'].value,
            },
            route: {
                method: 'PUT',
                url: '../rest/installer/administrator'
            },
            description: this.translate.instant('lang.stepUserAdminActionDesc'),
            installPriority: 3
        }];
    }

    launchInstall() {
        this.tiggerInstall.emit();
    }
}
