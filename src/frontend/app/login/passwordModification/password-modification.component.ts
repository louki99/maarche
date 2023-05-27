import { Component, OnInit, Inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { UntypedFormBuilder, UntypedFormGroup, Validators, ValidationErrors, AbstractControl, ValidatorFn } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialog } from '@angular/material/dialog';
import { AppService } from '@service/app.service';
import { Router } from '@angular/router';
import { HeaderService } from '@service/header.service';
import { AuthService } from '@service/auth.service';

@Component({
    templateUrl: 'password-modification.component.html'
})
export class PasswordModificationComponent implements OnInit {

    dialogRef: MatDialogRef<any>;
    config: any = {};


    loading: boolean = false;

    user: any = {};
    ruleText: string = '';
    otherRuleText: string;
    hidePassword: boolean = true;
    passLength: any = false;
    arrValidator: any[] = [];
    validPassword: boolean = false;
    matchPassword: boolean = false;
    isLinear: boolean = false;
    firstFormGroup: UntypedFormGroup;

    passwordRules: any = {
        minLength: { enabled: false, value: 0 },
        complexityUpper: { enabled: false, value: 0 },
        complexityNumber: { enabled: false, value: 0 },
        complexitySpecial: { enabled: false, value: 0 },
        renewal: { enabled: false, value: 0 },
        historyLastUse: { enabled: false, value: 0 },
    };

    passwordModel: any = {
        currentPassword: '',
        newPassword: '',
        reNewPassword: '',
    };


    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private authService: AuthService,
        private headerService: HeaderService,
        private notify: NotificationService,
        private _formBuilder: UntypedFormBuilder,
        public dialog: MatDialog,
        public appService: AppService
    ) {
        this.user =  JSON.parse(atob(this.authService.getToken().split('.')[1])).user;
    }

    ngOnInit(): void {
        setTimeout(() => {
            this.config = { panelClass: 'maarch-modal', data: { user: this.user, state: 'BEGIN' }, disableClose: true };
            this.dialogRef = this.dialog.open(InfoChangePasswordModalComponent, this.config);
        }, 0);

        this.http.get('../rest/passwordRules')
            .subscribe((data: any) => {
                const valArr: ValidatorFn[] = [];
                const ruleTextArr: String[] = [];
                const otherRuleTextArr: String[] = [];

                valArr.push(Validators.required);

                data.rules.forEach((rule: any) => {
                    if (rule.label === 'minLength') {
                        this.passwordRules.minLength.enabled = rule.enabled;
                        this.passwordRules.minLength.value = rule.value;
                        if (rule.enabled) {
                            valArr.push(Validators.minLength(this.passwordRules.minLength.value));
                            ruleTextArr.push(rule.value + ' ' + this.translate.instant('lang.password' + rule.label));
                        }
                    } else if (rule.label === 'complexityUpper') {
                        this.passwordRules.complexityUpper.enabled = rule.enabled;
                        this.passwordRules.complexityUpper.value = rule.value;
                        if (rule.enabled) {
                            valArr.push(this.regexValidator(new RegExp('[A-Z]'), { 'complexityUpper': '' }));
                            ruleTextArr.push(this.translate.instant('lang.password' + rule.label));
                        }
                    } else if (rule.label === 'complexityNumber') {
                        this.passwordRules.complexityNumber.enabled = rule.enabled;
                        this.passwordRules.complexityNumber.value = rule.value;
                        if (rule.enabled) {
                            valArr.push(this.regexValidator(new RegExp('[0-9]'), { 'complexityNumber': '' }));
                            ruleTextArr.push(this.translate.instant('lang.password' + rule.label));
                        }
                    } else if (rule.label === 'complexitySpecial') {
                        this.passwordRules.complexitySpecial.enabled = rule.enabled;
                        this.passwordRules.complexitySpecial.value = rule.value;
                        if (rule.enabled) {
                            valArr.push(this.regexValidator(new RegExp('[^A-Za-z0-9]'), { 'complexitySpecial': '' }));
                            ruleTextArr.push(this.translate.instant('lang.password' + rule.label));
                        }
                    } else if (rule.label === 'renewal') {
                        this.passwordRules.renewal.enabled = rule.enabled;
                        this.passwordRules.renewal.value = rule.value;
                        if (rule.enabled) {
                            otherRuleTextArr.push(this.translate.instant('lang.password' + rule.label) + ' <b>' + rule.value + ' ' + this.translate.instant('lang.days') + '</b>. ' + this.translate.instant('lang.password2' + rule.label) + '.');
                        }
                    } else if (rule.label === 'historyLastUse') {
                        this.passwordRules.historyLastUse.enabled = rule.enabled;
                        this.passwordRules.historyLastUse.value = rule.value;
                        if (rule.enabled) {
                            otherRuleTextArr.push(this.translate.instant('lang.passwordhistoryLastUseDesc') + ' <b>' + rule.value + '</b> ' + this.translate.instant('lang.passwordhistoryLastUseDesc2') + '.');
                        }
                    }
                });
                this.ruleText = ruleTextArr.join(', ');
                this.otherRuleText = otherRuleTextArr.join('<br/>');
                this.firstFormGroup.controls['newPasswordCtrl'].setValidators(valArr);
            }, (err: any) => {
                this.notify.error(err.error.errors);
            });

        this.firstFormGroup = this._formBuilder.group({
            newPasswordCtrl: [
                ''
            ],
            retypePasswordCtrl: [
                '',
                Validators.compose([Validators.required])
            ],
            currentPasswordCtrl: [
                '',
                Validators.compose([Validators.required])
            ]
        }, {
            validator: this.matchValidator
        });
    }

    regexValidator(regex: RegExp, error: ValidationErrors): ValidatorFn {
        return (control: AbstractControl): { [key: string]: any } => {
            if (!control.value) {
                return null;
            }
            const valid = regex.test(control.value);
            return valid ? null : error;
        };
    }

    matchValidator(group: UntypedFormGroup) {
        if (group.controls['newPasswordCtrl'].value === group.controls['retypePasswordCtrl'].value) {
            return false;
        } else {
            group.controls['retypePasswordCtrl'].setErrors({ 'mismatch': true });
            return { 'mismatch': true };
        }
    }

    getErrorMessage() {
        if (this.firstFormGroup.controls['newPasswordCtrl'].value !== this.firstFormGroup.controls['retypePasswordCtrl'].value) {
            this.firstFormGroup.controls['retypePasswordCtrl'].setErrors({ 'mismatch': true });
        } else {
            this.firstFormGroup.controls['retypePasswordCtrl'].setErrors(null);
        }
        if (this.firstFormGroup.controls['newPasswordCtrl'].hasError('required')) {
            return this.translate.instant('lang.requiredField') + ' !';
        } else if (this.firstFormGroup.controls['newPasswordCtrl'].hasError('minlength') && this.passwordRules.minLength.enabled) {
            return this.passwordRules.minLength.value + ' ' + this.translate.instant('lang.passwordminLength') + ' !';
        } else if (this.firstFormGroup.controls['newPasswordCtrl'].errors != null && this.firstFormGroup.controls['newPasswordCtrl'].errors.complexityUpper !== undefined && this.passwordRules.complexityUpper.enabled) {
            return this.translate.instant('lang.passwordcomplexityUpper') + ' !';
        } else if (this.firstFormGroup.controls['newPasswordCtrl'].errors != null && this.firstFormGroup.controls['newPasswordCtrl'].errors.complexityNumber !== undefined && this.passwordRules.complexityNumber.enabled) {
            return this.translate.instant('lang.passwordcomplexityNumber') + ' !';
        } else if (this.firstFormGroup.controls['newPasswordCtrl'].errors != null && this.firstFormGroup.controls['newPasswordCtrl'].errors.complexitySpecial !== undefined && this.passwordRules.complexitySpecial.enabled) {
            return this.translate.instant('lang.passwordcomplexitySpecial') + ' !';
        } else {
            this.firstFormGroup.controls['newPasswordCtrl'].setErrors(null);
            this.validPassword = true;
            return '';
        }
    }

    onSubmit() {
        this.passwordModel.currentPassword = this.firstFormGroup.controls['currentPasswordCtrl'].value;
        this.passwordModel.newPassword = this.firstFormGroup.controls['newPasswordCtrl'].value;
        this.passwordModel.reNewPassword = this.firstFormGroup.controls['retypePasswordCtrl'].value;
        this.http.put('../rest/users/' + this.user.id + '/password', this.passwordModel)
            .subscribe(() => {
                this.config = { panelClass: 'maarch-modal', data: { state: 'END' }, disableClose: true };
                this.dialogRef = this.dialog.open(InfoChangePasswordModalComponent, this.config);
            }, (err: any) => {
                this.notify.error(err.error.errors);
            });
    }

    logout() {
        this.authService.logout();
    }
}

@Component({
    templateUrl: 'info-change-password-modal.component.html'
})
export class InfoChangePasswordModalComponent {



    constructor(
        public http: HttpClient,
        private router: Router,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<InfoChangePasswordModalComponent>
    ) { }

    redirect() {
        this.router.navigate(['/home']);
        this.dialogRef.close();
    }
}
