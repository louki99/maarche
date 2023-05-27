import { AfterViewInit, Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router, ActivatedRoute } from '@angular/router';
import { NotificationService } from '@service/notification/notification.service';
import { TranslateService } from '@ngx-translate/core';
import { finalize } from 'rxjs/operators';
import { HeaderService } from '@service/header.service';

@Component({
    templateUrl: 'reset-password.component.html',
    styleUrls: ['reset-password.component.scss'],
})
export class ResetPasswordComponent implements OnInit, AfterViewInit {


    loadingForm: boolean = false;
    loading: boolean = false;

    token: string = '';

    password: any = {
        newPassword: '',
        passwordConfirmation: ''
    };
    labelButton: string = this.translate.instant('lang.update');

    hideNewPassword: Boolean = true;
    hideNewPasswordConfirm: Boolean = true;

    // HANDLE PASSWORD
    passwordRules: any = {
        minLength: { enabled: false, value: 0 },
        complexityUpper: { enabled: false, value: 0 },
        complexityNumber: { enabled: false, value: 0 },
        complexitySpecial: { enabled: false, value: 0 },
        renewal: { enabled: false, value: 0 },
        historyLastUse: { enabled: false, value: 0 },
    };

    handlePassword: any = {
        error: false,
        errorMsg: ''
    };

    ruleText = '';
    otherRuleText = '';


    constructor(
        public translate: TranslateService,
        private router: Router,
        private route: ActivatedRoute,
        public http: HttpClient,
        public notificationService: NotificationService,
        private headerService: HeaderService,
    ) {
        this.route.queryParams.subscribe(params => {
            this.token = params.token;
        });
    }

    ngOnInit(): void {
        this.headerService.hideSideBar = true;
        this.getPassRules();
    }

    ngAfterViewInit(): void {
        // FIX lang not loaded yet
        this.labelButton = this.translate.instant('lang.update');
    }


    updatePassword() {
        this.labelButton = this.translate.instant('lang.emailSendInProgressShort');
        this.loading = true;

        this.http.put('../rest/password', { 'token': this.token, 'password': this.password.newPassword })
            .pipe(
                finalize(() => {
                    this.labelButton = this.translate.instant('lang.update');
                    this.loading = false;
                })
            )
            .subscribe((data: any) => {
                this.loadingForm = true;
                this.notificationService.success(this.translate.instant('lang.passwordChanged'));
                this.router.navigate(['/login']);
            }, (err: any) => {
                this.notificationService.handleSoftErrors(err);
            });
    }

    checkPasswordValidity(password: string) {
        this.handlePassword.error = true;

        if (!password.match(/[A-Z]/g) && this.passwordRules.complexityUpper.enabled) {
            this.handlePassword.errorMsg = this.translate.instant('lang.passwordcomplexityUpperRequired');
        } else if (!password.match(/[0-9]/g) && this.passwordRules.complexityNumber.enabled) {
            this.handlePassword.errorMsg = this.translate.instant('lang.passwordcomplexityNumberRequired');
        } else if (!password.match(/[^A-Za-z0-9]/g) && this.passwordRules.complexitySpecial.enabled) {
            this.handlePassword.errorMsg = this.translate.instant('lang.passwordcomplexitySpecialRequired');
        } else if (password.length < this.passwordRules.minLength.value && this.passwordRules.minLength.enabled) {
            this.handlePassword.errorMsg = this.passwordRules.minLength.value + ' ' + this.translate.instant('lang.passwordminLength') + ' !';
        } else {
            this.handlePassword.error = false;
            this.handlePassword.errorMsg = '';
        }
    }

    getPassRules() {
        this.handlePassword.error = false;
        this.handlePassword.errorMsg = '';

        this.http.get('../rest/passwordRules')
            .subscribe((data: any) => {
                const ruleTextArr: String[] = [];
                const otherRuleTextArr: String[] = [];

                data.rules.forEach((rule: any) => {
                    if (rule.label === 'minLength') {
                        this.passwordRules.minLength.enabled = rule.enabled;
                        this.passwordRules.minLength.value = rule.value;
                        if (rule.enabled) {
                            ruleTextArr.push(rule.value + ' ' + this.translate.instant('lang.password' + rule.label));

                        }

                    } else if (rule.label === 'complexityUpper') {
                        this.passwordRules.complexityUpper.enabled = rule.enabled;
                        this.passwordRules.complexityUpper.value = rule.value;
                        if (rule.enabled) {
                            ruleTextArr.push(this.translate.instant('lang.password' + rule.label));
                        }

                    } else if (rule.label === 'complexityNumber') {
                        this.passwordRules.complexityNumber.enabled = rule.enabled;
                        this.passwordRules.complexityNumber.value = rule.value;
                        if (rule.enabled) {
                            ruleTextArr.push(this.translate.instant('lang.password' + rule.label));
                        }

                    } else if (rule.label === 'complexitySpecial') {
                        this.passwordRules.complexitySpecial.enabled = rule.enabled;
                        this.passwordRules.complexitySpecial.value = rule.value;
                        if (rule.enabled) {
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
            }, (err) => {
                this.notificationService.handleErrors(err);
            });
    }

    allowValidate() {
        if ((this.handlePassword.error || this.password.newPassword !== this.password.passwordConfirmation || this.password.newPassword.length === 0 || this.password.passwordConfirmation.length === 0)) {
            return true;
        } else {
            return false;
        }
    }

    cancel() {
        this.router.navigate(['/login']);
    }
}
