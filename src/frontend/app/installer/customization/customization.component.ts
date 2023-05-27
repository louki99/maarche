import { Component, Input, OnInit } from '@angular/core';
import { UntypedFormBuilder, UntypedFormGroup, Validators, ValidatorFn } from '@angular/forms';
import { TranslateService } from '@ngx-translate/core';
import { StepAction } from '../types';
import { DomSanitizer } from '@angular/platform-browser';
import { NotificationService } from '@service/notification/notification.service';
import { environment } from '../../../environments/environment';
import { ScanPipe } from 'ngx-pipes';
import { debounceTime, filter, tap, catchError, startWith } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { InstallerService } from '../installer.service';
import { of } from 'rxjs';
import { DatabaseComponent } from '../database/database.component';
import { WelcomeComponent } from '../welcome/welcome.component';
import { FunctionsService } from '@service/functions.service';

declare let tinymce: any;

@Component({
    selector: 'app-customization',
    templateUrl: './customization.component.html',
    styleUrls: ['./customization.component.scss'],
    providers: [ScanPipe]
})
export class CustomizationComponent implements OnInit {

    @Input() appDatabase: DatabaseComponent;
    @Input() appWelcome: WelcomeComponent;

    stepFormGroup: UntypedFormGroup;
    readonlyState: boolean = false;

    backgroundList: any[] = [];

    constructor(
        public translate: TranslateService,
        private _formBuilder: UntypedFormBuilder,
        private notify: NotificationService,
        private sanitizer: DomSanitizer,
        private scanPipe: ScanPipe,
        public http: HttpClient,
        private installerService: InstallerService,
        private functionsService: FunctionsService
    ) {
        const valIdentifier: ValidatorFn[] = [Validators.pattern(/^[a-z0-9_\-]*$/), Validators.required, Validators.minLength(3)];

        const docUrl: string = this.functionsService.getDocBaseUrl();

        this.stepFormGroup = this._formBuilder.group({
            firstCtrl: ['success', Validators.required],
            customId: [null, valIdentifier],
            appName: [`Maarch Courrier ${environment.BASE_VERSION}`, Validators.required],
            loginMessage: [`<span style="color:#24b0ed"><strong>Découvrez votre application via</strong></span>&nbsp;<a title="le guide de visite" href="${docUrl}/guu/home.html" target="_blank"><span style="color:#f99830;"><strong>le guide de visite en ligne</strong></span></a>`],
            homeMessage: [`<p>D&eacute;couvrez <strong>Maarch Courrier ${environment.BASE_VERSION}</strong> avec <a title="notre guide de visite" href="${docUrl}" target="_blank"><span style="color:#f99830;"><strong>notre guide de visite en ligne</strong></span></a>.</p>`],
            bodyLoginBackground: ['assets/bodylogin.jpg'],
            uploadedLogo: ['../rest/images?image=logo'],
        });
        this.backgroundList = Array.from({ length: 17 }).map((_, i) => ({
            filename: `${i + 1}.jpg`,
            url: `assets/${i + 1}.jpg`,
        }));
    }

    ngOnInit(): void {
        this.checkCustomExist();
        this.stepFormGroup.controls['customId'].valueChanges.pipe(
            startWith(''),
            tap(() => {
                this.stepFormGroup.controls['firstCtrl'].setValue('');
            }),
            debounceTime(500),
            filter((value: any) => value.length > 2),
            filter(() => this.stepFormGroup.controls['customId'].errors === null || this.stepFormGroup.controls['customId'].errors.pattern === undefined),
            tap(() => {
                this.checkCustomExist();
            }),
        ).subscribe();
    }

    initStep() {
        if (this.stepFormGroup.controls['customId'].value === null) {
            this.stepFormGroup.controls['customId'].setValue(this.appDatabase.getInfoToInstall()[0].body.name);
        }
        if (this.installerService.isStepAlreadyLaunched('createCustom') && this.installerService.isStepAlreadyLaunched('customization')) {
            this.stepFormGroup.disable();
            this.readonlyState = true;
            tinymce.remove();
            this.initMce(true);
        } else if (this.installerService.isStepAlreadyLaunched('createCustom')) {
            this.stepFormGroup.controls['customId'].disable();
            this.stepFormGroup.controls['appName'].disable();
            this.stepFormGroup.controls['firstCtrl'].disable();
        } else if (this.installerService.isStepAlreadyLaunched('customization')) {
            this.stepFormGroup.controls['loginMessage'].disable();
            this.stepFormGroup.controls['homeMessage'].disable();
            this.stepFormGroup.controls['bodyLoginBackground'].disable();
            this.stepFormGroup.controls['uploadedLogo'].disable();
            this.readonlyState = true;
            tinymce.remove();
            this.initMce(true);
        } else {
            this.readonlyState = false;
            this.initMce();
        }
    }

    checkCustomExist() {
        this.http.get('../rest/installer/custom', { observe: 'response', params: { 'customId': this.stepFormGroup.controls['customId'].value } }).pipe(
            tap((response: any) => {
                if (this.stepFormGroup.controls['customId'].errors !== null) {
                    const error = this.stepFormGroup.controls['customId'].errors;
                    delete error.customExist;
                } else {
                    this.stepFormGroup.controls['firstCtrl'].setValue('success');
                }
            }),
            catchError((err: any) => {
                const regex = /^Custom already exists/g;
                const regexInvalid = /^Unauthorized custom name/g;
                if (err.error.errors.match(regex) !== null) {
                    this.stepFormGroup.controls['customId'].setErrors({ ...this.stepFormGroup.controls['customId'].errors, customExist: true });
                    this.stepFormGroup.controls['customId'].markAsTouched();
                }else if (err.error.errors.match(regexInvalid) !== null) {
                    this.stepFormGroup.controls['customId'].setErrors({ ...this.stepFormGroup.controls['customId'].errors, invalidCustomName: true });
                    this.stepFormGroup.controls['customId'].markAsTouched();
                } else {
                    this.notify.handleSoftErrors(err);
                }
                return of(false);
            })
        ).subscribe();
    }

    isValidStep() {
        if (this.installerService.isStepAlreadyLaunched('createCustom') && this.installerService.isStepAlreadyLaunched('customization')) {
            return true;
        } else {
            return this.stepFormGroup === undefined ? false : this.stepFormGroup.valid;
        }
    }

    getFormGroup() {
        return this.installerService.isStepAlreadyLaunched('createCustom') && this.installerService.isStepAlreadyLaunched('customization') ? true : this.stepFormGroup;
    }

    initMce(readonly = false) {
        tinymce.init({
            selector: 'textarea',
            base_url: '../node_modules/tinymce/',
            convert_urls: false,
            height: '150',
            suffix: '.min',
            language: this.translate.instant('lang.langISO').replace('-', '_'),
            language_url: `../node_modules/tinymce-i18n/langs/${this.translate.instant('lang.langISO').replace('-', '_')}.js`,
            menubar: false,
            statusbar: false,
            readonly: readonly,
            plugins: [
                'autolink'
            ],
            external_plugins: {
                'maarch_b64image': '../../src/frontend/plugins/tinymce/maarch_b64image/plugin.min.js'
            },
            toolbar_sticky: true,
            toolbar_mode: 'floating',
            toolbar: !readonly ? 'undo redo | fontselect fontsizeselect | bold italic underline strikethrough forecolor | maarch_b64image | \
        alignleft aligncenter alignright alignjustify \
        bullist numlist outdent indent | removeformat' : ''
        });
    }

    getInfoToInstall(): StepAction[] {
        return [
            {
                idStep: 'createCustom',
                body: {
                    lang: this.appWelcome.stepFormGroup.controls['lang'].value,
                    customId: this.stepFormGroup.controls['customId'].value,
                    applicationName: this.stepFormGroup.controls['appName'].value,
                },
                description: this.translate.instant('lang.stepInstanceActionDesc'),
                route: {
                    method: 'POST',
                    url: '../rest/installer/custom'
                },
                installPriority: 1
            },
            {
                idStep: 'customization',
                body: {
                    loginMessage: tinymce.get('loginMessage').getContent(),
                    homeMessage: tinymce.get('homeMessage').getContent(),
                    bodyLoginBackground: this.stepFormGroup.controls['bodyLoginBackground'].value,
                    logo: this.stepFormGroup.controls['uploadedLogo'].value,
                },
                description: this.translate.instant('lang.stepCustomizationActionDesc'),
                route: {
                    method: 'POST',
                    url: '../rest/installer/customization'
                },
                installPriority: 3
            }
        ];
    }

    uploadTrigger(fileInput: any, mode: string) {
        if (fileInput.target.files && fileInput.target.files[0]) {
            const res = this.canUploadFile(fileInput.target.files[0], mode);
            if (res === true) {
                const reader = new FileReader();

                reader.readAsDataURL(fileInput.target.files[0]);
                reader.onload = (value: any) => {
                    if (mode === 'logo') {
                        this.stepFormGroup.controls['uploadedLogo'].setValue(value.target.result);
                    } else {
                        const img = new Image();
                        img.onload = (imgDim: any) => {
                            if (imgDim.target.width < 1920 || imgDim.target.height < 1080) {
                                this.notify.error(this.translate.instant('lang.badImageResolution', {value1: '1920x1080'}));
                            } else {
                                this.backgroundList.push({
                                    filename: value.target.result,
                                    url: value.target.result,
                                });
                                this.stepFormGroup.controls['bodyLoginBackground'].setValue(value.target.result);
                            }
                        };
                        img.src = value.target.result;
                    }
                };
            } else {
                this.notify.error(res);
            }
        }
    }

    canUploadFile(file: any, mode: string) {
        const allowedExtension = mode !== 'logo' ? ['image/jpg', 'image/jpeg'] : ['image/svg+xml'];

        if (mode === 'logo') {
            if (file.size > 5000000) {
                return this.translate.instant('lang.maxFileSizeExceeded', {value1: '5mo'});
            } else if (allowedExtension.indexOf(file.type) === -1) {
                return this.translate.instant('lang.onlyExtensionsAllowed', {value1: allowedExtension.join(', ')});
            }
        } else {
            if (file.size > 10000000) {
                return this.translate.instant('lang.maxFileSizeExceeded', {value1: '10mo'});
            } else if (allowedExtension.indexOf(file.type) === -1) {
                return this.translate.instant('lang.onlyExtensionsAllowed', {value1: allowedExtension.join(', ')});
            }
        }
        return true;
    }

    logoURL() {
        return this.sanitizer.bypassSecurityTrustUrl(this.stepFormGroup.controls['uploadedLogo'].value);
    }

    selectBg(content: string) {
        if (!this.stepFormGroup.controls['bodyLoginBackground'].disabled) {
            this.stepFormGroup.controls['bodyLoginBackground'].setValue(content);
        }
    }

    clickLogoButton(uploadLogo: any) {
        if (!this.stepFormGroup.controls['uploadedLogo'].disabled) {
            uploadLogo.click();
        }
    }
}
