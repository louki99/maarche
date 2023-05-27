import { Component, OnInit, OnDestroy } from '@angular/core';
import { UntypedFormBuilder, UntypedFormGroup, Validators, ValidatorFn } from '@angular/forms';
import { TranslateService } from '@ngx-translate/core';
import { DomSanitizer } from '@angular/platform-browser';
import { NotificationService } from '@service/notification/notification.service';
import { ScanPipe } from 'ngx-pipes';
import { debounceTime, tap, catchError, exhaustMap } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { of } from 'rxjs';

declare let tinymce: any;

@Component({
    selector: 'app-parameters-customization',
    templateUrl: './parameters-customization.component.html',
    styleUrls: ['./parameters-customization.component.scss'],
    providers: [ScanPipe]
})
export class ParametersCustomizationComponent implements OnInit, OnDestroy {
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
    ) {
        const valIdentifier: ValidatorFn[] = [Validators.pattern(/^[a-zA-Z0-9_\-]*$/), Validators.required];

        this.stepFormGroup = this._formBuilder.group({
            applicationName: ['', Validators.required],
            maarchUrl: ['', Validators.required],
            loginpage_message: [''],
            homepage_message: [''],
            traffic_record_summary_sheet: [''],
            bodyImage: ['../rest/images?image=loginPage'],
            logo: ['../rest/images?image=logo'],
        });

        this.backgroundList = Array.from({ length: 17 }).map((_, i) => ({
            filename: `${i + 1}.jpg`,
            url: `assets/${i + 1}.jpg`,
        }));
    }

    async ngOnInit(): Promise<void> {
        await this.getParameters();
    }

    getParameters() {
        return new Promise(() => {
            this.http.get('../rest/parameters').pipe(
                tap((data: any) => {
                    this.stepFormGroup.controls['homepage_message'].setValue(data.parameters.filter((item: any) => item.id === 'homepage_message')[0].value);
                    this.stepFormGroup.controls['loginpage_message'].setValue(data.parameters.filter((item: any) => item.id === 'loginpage_message')[0].value);
                    this.stepFormGroup.controls['traffic_record_summary_sheet'].setValue(data.parameters.filter((item: any) => item.id === 'traffic_record_summary_sheet')[0].value);
                }),
                exhaustMap(() => this.http.get('../rest/authenticationInformations')),
                tap((data: any) => {
                    this.stepFormGroup.controls['applicationName'].setValue(data.applicationName);
                    this.stepFormGroup.controls['maarchUrl'].setValue(data.maarchUrl);
                    setTimeout(() => {

                        this.stepFormGroup.controls['applicationName'].valueChanges.pipe(
                            debounceTime(1000),
                            tap(() => this.saveParameter('applicationName'))
                        ).subscribe();

                        this.stepFormGroup.controls['maarchUrl'].valueChanges.pipe(
                            debounceTime(1000),
                            tap(() => this.saveParameter('maarchUrl'))
                        ).subscribe();

                        this.stepFormGroup.controls['homepage_message'].valueChanges.pipe(
                            debounceTime(100),
                            tap(() => this.saveParameter('homepage_message'))
                        ).subscribe();

                        this.stepFormGroup.controls['loginpage_message'].valueChanges.pipe(
                            debounceTime(100),
                            tap(() => this.saveParameter('loginpage_message'))
                        ).subscribe();

                        this.stepFormGroup.controls['traffic_record_summary_sheet'].valueChanges.pipe(
                            debounceTime(100),
                            tap(() => this.saveParameter('traffic_record_summary_sheet'))
                        ).subscribe();
                        this.initMce();
                    }, 100);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    initMce(readonly = false) {
        const param = {
            selector: '#loginpage_message',
            setup: (editor: any) => {
                editor.on('Blur', (e) => {
                    this.stepFormGroup.controls[e.target.id].setValue(tinymce.get(e.target.id).getContent());
                });
            },
            base_url: '../node_modules/tinymce/',
            convert_urls: false,
            height: '200',
            suffix: '.min',
            language: this.translate.instant('lang.langISO').replace('-', '_'),
            language_url: `../node_modules/tinymce-i18n/langs/${this.translate.instant('lang.langISO').replace('-', '_')}.js`,
            menubar: false,
            statusbar: false,
            readonly: readonly,
            plugins: [
                'autolink', 'table', 'code'
            ],
            external_plugins: {
                'maarch_b64image': '../../src/frontend/plugins/tinymce/maarch_b64image/plugin.min.js'
            },
            table_toolbar: '',
            table_sizing_mode: 'relative',
            table_resize_bars: false,
            toolbar_sticky: true,
            toolbar_mode: 'floating',
            table_style_by_css: true,
            content_style: 'table td { padding: 1px; vertical-align: top; }',
            forced_root_block : false,
            toolbar: !readonly ? 'undo redo | fontselect fontsizeselect | bold italic underline strikethrough forecolor | table maarch_b64image | \
        alignleft aligncenter alignright alignjustify \
        bullist numlist outdent indent | removeformat code' : ''
        };
        tinymce.init(param);
        param.selector = '#homepage_message';
        tinymce.init(param);
        param.selector = '#traffic_record_summary_sheet';
        param.height = '500';
        tinymce.init(param);
    }

    uploadTrigger(fileInput: any, mode: string) {
        if (fileInput.target.files && fileInput.target.files[0]) {
            const res = this.canUploadFile(fileInput.target.files[0], mode);
            if (res === true) {
                const reader = new FileReader();

                reader.readAsDataURL(fileInput.target.files[0]);
                reader.onload = (value: any) => {
                    if (mode === 'logo') {
                        this.stepFormGroup.controls['logo'].setValue(value.target.result);
                        this.saveParameter('logo');
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
                                this.stepFormGroup.controls['bodyImage'].setValue(value.target.result);
                                this.saveParameter('bodyImage');
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

    selectBg(content: string) {
        if (!this.stepFormGroup.controls['bodyImage'].disabled) {
            this.stepFormGroup.controls['bodyImage'].setValue(content);
            this.saveParameter('bodyImage');
        }
    }

    clickLogoButton(uploadLogo: any) {
        if (!this.stepFormGroup.controls['logo'].disabled) {
            uploadLogo.click();
        }
    }

    saveParameter(parameterId: string) {
        let param = {};
        if (parameterId === 'logo' || parameterId === 'bodyImage') {
            param['image'] = this.stepFormGroup.controls[parameterId].value;
        } else if (parameterId === 'applicationName' || parameterId === 'maarchUrl') {
            param[parameterId] = this.stepFormGroup.controls[parameterId].value;
        } else {
            param = {
                param_value_string: this.stepFormGroup.controls[parameterId].value
            };
        }
        this.http.put('../rest/parameters/' + parameterId, param)
            .subscribe(() => {
                this.notify.success(this.translate.instant('lang.parameterUpdated'));
                if (parameterId === 'logo') {
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    ngOnDestroy(): void {
        tinymce.remove();
    }
}
