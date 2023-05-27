import { Component, OnInit, NgZone, ViewChild, QueryList, ViewChildren, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { MatExpansionPanel } from '@angular/material/expansion';
import { MatSidenav } from '@angular/material/sidenav';
import { UntypedFormGroup, Validators, AbstractControl, ValidationErrors, ValidatorFn, UntypedFormBuilder } from '@angular/forms';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { AuthService } from '@service/auth.service';
import { AbsModalComponent } from './absModal/abs-modal.component';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { of, Subscription } from 'rxjs';

declare let $: any;

@Component({
    templateUrl: 'profile.component.html',
    styleUrls: ['profile.component.css']
})
export class ProfileComponent implements OnInit {

    @ViewChild('snav2', { static: true }) sidenavRight: MatSidenav;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;

    @ViewChildren(MatExpansionPanel) viewPanels: QueryList<MatExpansionPanel>;

    // Redirect Baskets
    myBasketExpansionPanel: boolean = false;

    dialogRef: MatDialogRef<any>;

    subscription: Subscription;

    highlightMe: boolean = false;
    user: any = {
        baskets: []
    };
    histories: any[] = [];
    passwordModel: any = {
        currentPassword: '',
        newPassword: '',
        reNewPassword: '',
    };
    firstFormGroup: UntypedFormGroup;
    ruleText: string = '';
    otherRuleText: string;
    validPassword: Boolean = false;
    matchPassword: Boolean = false;
    hidePassword: Boolean = true;
    passwordRules: any = {
        minLength: { enabled: false, value: 0 },
        complexityUpper: { enabled: false, value: 0 },
        complexityNumber: { enabled: false, value: 0 },
        complexitySpecial: { enabled: false, value: 0 },
        renewal: { enabled: false, value: 0 },
        historyLastUse: { enabled: false, value: 0 },
    };
    signatureModel: any = {
        base64: '',
        base64ForJs: '',
        name: '',
        type: '',
        size: 0,
        label: '',
    };
    mailSignatureModel: any = {
        selected: -1,
        htmlBody: '',
        title: '',
    };
    userAbsenceModel: any[] = [];
    basketsToRedirect: string[] = [];

    showPassword: boolean = false;
    selectedSignature: number = -1;
    selectedSignatureLabel: string = '';
    loading: boolean = false;
    selectedIndex: number = 0;
    loadingSign: boolean = false;

    haveAbsScheduled: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialog: MatDialog,
        public headerService: HeaderService,
        public appService: AppService,
        public authService: AuthService,
        private zone: NgZone,
        private notify: NotificationService,
        private _formBuilder: UntypedFormBuilder,
        private viewContainerRef: ViewContainerRef,
        private functions: FunctionsService,
    ) {
        this.subscription = this.authService.catchEvent().subscribe((result: any) => {
            if (result === 'isAbsScheduled') {
                this.haveAbsScheduled = result === 'isAbsScheduled' ? true : false;
            } else if (result === 'absOff') {
                this.haveAbsScheduled = false;
            }
        });
    }

    initComponents(event: any) {
        this.selectedIndex = event.index;
        if (event.index === 2) {
            if (!this.appService.getViewMode()) {
                this.sidenavRight.open();
            }
        } else if (event.index === 1) {
            this.sidenavRight.close();
        } else if (!this.appService.getViewMode()) {
            this.sidenavRight.open();
        }
    }

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.myProfile'));
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.loading = true;

        this.http.get('../rest/currentUser/profile')
            .subscribe((data: any) => {
                if (data.absence) {
                    const startDate: string = data.absence.absenceDate.startDate;
                    this.haveAbsScheduled = startDate !== this.functions.formatDateObjectToDateString(new Date()) ? true : false;
                }
                this.user = data;
                this.user.baskets = this.user.baskets.filter((basket: any) => !basket.basketSearch);
                this.user.baskets.forEach((value: any, index: number) => {
                    this.user.baskets[index]['disabled'] = false;
                    this.user.redirectedBaskets.forEach((value2: any) => {
                        if (value.basket_id == value2.basket_id && value.basket_owner == value2.basket_owner) {
                            this.user.baskets[index]['disabled'] = true;
                        }
                    });
                });
                this.loading = false;
            });
    }

    displayPassword() {
        this.showPassword = !this.showPassword;
    }

    displaySignatureEditionForm(index: number) {
        this.selectedSignature = index;
        this.selectedSignatureLabel = this.user.signatures[index].signature_label;
    }

    delBasketRedirection(basket: any, i: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.deleteRedirection')}`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/users/' + this.user.id + '/redirectedBaskets?redirectedBasketIds[]=' + basket.id)),
            tap((data: any) => {
                this.user.baskets = data['baskets'].filter((basketItem: any) => !basketItem.basketSearch);
                this.user.redirectedBaskets.splice(i, 1);
                this.notify.success(this.translate.instant('lang.basketUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    delBasketAssignRedirection(basket: any, i: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.deleteAssignation')}`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/users/' + this.user.id + '/redirectedBaskets?redirectedBasketIds[]=' + basket.id)),
            tap((data: any) => {
                this.user.baskets = data['baskets'].filter((basketItem: any) => !basketItem.basketSearch);
                this.user.assignedBaskets.splice(i, 1);
                this.notify.success(this.translate.instant('lang.basketUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    openAbsModal() {
        this.dialog.open(AbsModalComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: this.user.baskets.length === 0 ? 'auto' : '700px',
            data: {
                user: this.user
            }
        });
    }

    updatePassword() {
        this.passwordModel.currentPassword = this.firstFormGroup.controls['currentPasswordCtrl'].value;
        this.passwordModel.newPassword = this.firstFormGroup.controls['newPasswordCtrl'].value;
        this.passwordModel.reNewPassword = this.firstFormGroup.controls['retypePasswordCtrl'].value;
        this.http.put('../rest/users/' + this.user.id + '/password', this.passwordModel)
            .subscribe((data: any) => {
                this.showPassword = false;
                this.passwordModel = {
                    currentPassword: '',
                    newPassword: '',
                    reNewPassword: '',
                };
                this.notify.success(this.translate.instant('lang.passwordUpdated'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    onSubmit() {
        this.http.put('../rest/currentUser/profile', this.user)
            .subscribe(() => {
                this.notify.success(this.translate.instant('lang.modificationSaved'));
                this.headerService.user.firstname = this.user.firstname;
                this.headerService.user.lastname = this.user.lastname;
                this.headerService.user.mail = this.user.mail;
            }, (err) => {
                this.notify.error(err.error.errors);
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

    changePasswd() {
        this.http.get('../rest/passwordRules')
            .subscribe((data: any) => {
                const valArr: ValidatorFn[] = [];
                const ruleTextArr: String[] = [];
                const otherRuleTextArr: String[] = [];

                valArr.push(Validators.required);

                data.rules.forEach((rule: any) => {
                    if (rule.label == 'minLength') {
                        this.passwordRules.minLength.enabled = rule.enabled;
                        this.passwordRules.minLength.value = rule.value;
                        if (rule.enabled) {
                            valArr.push(Validators.minLength(this.passwordRules.minLength.value));
                            ruleTextArr.push(rule.value + ' ' + this.translate.instant('lang.password' + rule.label));
                        }


                    } else if (rule.label == 'complexityUpper') {
                        this.passwordRules.complexityUpper.enabled = rule.enabled;
                        this.passwordRules.complexityUpper.value = rule.value;
                        if (rule.enabled) {
                            valArr.push(this.regexValidator(new RegExp('[A-Z]'), { 'complexityUpper': '' }));
                            ruleTextArr.push(this.translate.instant('lang.password' + rule.label));
                        }


                    } else if (rule.label == 'complexityNumber') {
                        this.passwordRules.complexityNumber.enabled = rule.enabled;
                        this.passwordRules.complexityNumber.value = rule.value;
                        if (rule.enabled) {
                            valArr.push(this.regexValidator(new RegExp('[0-9]'), { 'complexityNumber': '' }));
                            ruleTextArr.push(this.translate.instant('lang.password' + rule.label));
                        }


                    } else if (rule.label == 'complexitySpecial') {
                        this.passwordRules.complexitySpecial.enabled = rule.enabled;
                        this.passwordRules.complexitySpecial.value = rule.value;
                        if (rule.enabled) {
                            valArr.push(this.regexValidator(new RegExp('[^A-Za-z0-9]'), { 'complexitySpecial': '' }));
                            ruleTextArr.push(this.translate.instant('lang.password' + rule.label));
                        }
                    } else if (rule.label == 'renewal') {
                        this.passwordRules.renewal.enabled = rule.enabled;
                        this.passwordRules.renewal.value = rule.value;
                        if (rule.enabled) {
                            otherRuleTextArr.push(this.translate.instant('lang.password' + rule.label) + ' <b>' + rule.value + ' ' + this.translate.instant('lang.days') + '</b>. ' + this.translate.instant('lang.password2' + rule.label) + '.');
                        }
                    } else if (rule.label == 'historyLastUse') {
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
            }, (err) => {
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
        this.validPassword = false;
        this.firstFormGroup.controls['currentPasswordCtrl'].setErrors(null);
        this.firstFormGroup.controls['newPasswordCtrl'].setErrors(null);
        this.firstFormGroup.controls['retypePasswordCtrl'].setErrors(null);
        this.selectedIndex = 0;
        this.showPassword = true;
    }

    matchValidator(group: UntypedFormGroup) {

        if (group.controls['newPasswordCtrl'].value == group.controls['retypePasswordCtrl'].value) {
            return false;
        } else {
            group.controls['retypePasswordCtrl'].setErrors({ 'mismatch': true });
            return { 'mismatch': true };
        }
    }

    getErrorMessage() {
        if (this.firstFormGroup.controls['newPasswordCtrl'].value != this.firstFormGroup.controls['retypePasswordCtrl'].value) {
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

    toggleRedirection(value: any) {
        if (value.event === 'add') {
            value.redirectedBaskets.forEach((val: any) => {
                this.user.baskets.find((item: any) => val.basket_id === item.basket_id && val.group_id === item.groupSerialId).userToDisplay = val.userToDisplay;
            });
        } else if (value.event === 'del') {
            this.user.baskets.find((item: any) => value.baskeToDel[0].basket_id === item.basket_id && value.baskeToDel[0].group_id === item.groupSerialId).userToDisplay = null;
        }
        this.user.redirectedBaskets = value.redirectedBaskets;
    }
}
