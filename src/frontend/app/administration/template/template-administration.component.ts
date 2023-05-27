import { Component, OnInit, ViewChild, Inject, TemplateRef, ViewContainerRef, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router, ActivatedRoute } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatSidenav } from '@angular/material/sidenav';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { filter, tap, catchError, exhaustMap } from 'rxjs/operators';
import { FunctionsService } from '@service/functions.service';
import { of } from 'rxjs';
import { TemplateFileEditorModalComponent } from './templateFileEditorModal/template-file-editor-modal.component';
import { DomSanitizer } from '@angular/platform-browser';
import { AlertComponent } from '../../../plugins/modal/alert.component';
import { MaarchFlatTreeComponent } from '../../../plugins/tree/maarch-flat-tree.component';
import { AuthService } from '@service/auth.service';
import { ConfirmComponent } from '@plugins/modal/confirm.component';

declare let tinymce: any;

@Component({
    templateUrl: 'template-administration.component.html',
    styleUrls: ['template-administration.component.scss']
})
export class TemplateAdministrationComponent implements OnInit, OnDestroy {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild('maarchTree', { static: true }) maarchTree: MaarchFlatTreeComponent;

    loading: boolean = false;

    creationMode: boolean;

    template: any = {
        id: 0,
        label: '',
        description: '',
        datasource: 'letterbox_attachment',
        target: '',
        type: '',
        file: null
    };

    targetTypes: string[] = [
        'acknowledgementReceipt',
        'notes',
        'sendmail',
        'indexingFile',
        'notifications',
        'attachments'
    ];

    allowedExtensions: string[] = [
        'doc',
        'docx',
        'dotx',
        'odt',
        'ott',
        'html',
        'xlsl',
        'xlsx',
        'xltx',
        'ods',
        'ots',
        'csv',
    ];

    selectedModelFile: any = null;
    availableTypes: string[] = [];

    statuses: any[] = [];
    categoriesList: any[] = [];
    keywordsList: any[] = [];
    defaultTemplatesList: any;
    attachmentTypesList: any;
    datasourcesList: any;
    jnlpValue: any = {};
    extensionModels: any[] = [];
    buttonFileName: any = this.translate.instant('lang.importFile');
    lockFound: boolean = false;
    intervalLockFile: any;

    templateDocView: any = null;

    dialogRef: MatDialogRef<any>;
    data: any[] = [];
    config: any = {};

    documentImported: boolean = false;

    acknowledgementReceiptFrom = [
        {
            id: 'manual',
            label : this.translate.instant('lang.manual')
        },
        {
            id: 'destination',
            label : this.translate.instant('lang.destination')
        },
        {
            id: 'mailServer',
            label : this.translate.instant('lang.mailServer')
        },
        {
            id: 'user',
            label : this.translate.instant('lang.user')
        }
    ];

    options: any = {};

    constructor(
        public http: HttpClient,
        private sanitizer: DomSanitizer,
        private route: ActivatedRoute,
        private router: Router,
        private notify: NotificationService,
        public headerService: HeaderService,
        public dialog: MatDialog,
        public appService: AppService,
        private viewContainerRef: ViewContainerRef,
        public functionsService: FunctionsService,
        public translate: TranslateService,
        private authService: AuthService
    ) { }

    ngOnInit(): void {
        this.loading = true;
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.route.params.subscribe(params => {
            if (typeof params['id'] === 'undefined') {
                this.headerService.setHeader(this.translate.instant('lang.templateCreation'));

                this.creationMode = true;

                this.http.get('../rest/administration/templates/new')
                    .subscribe((data: any) => {
                        this.setInitialValue(data);
                        this.loading = false;

                    });
            } else {

                this.creationMode = false;
                this.http.get('../rest/templates/' + params['id'] + '/details')
                    .subscribe((data: any) => {
                        this.setInitialValue(data);
                        this.template = {
                            id: data.template.template_id,
                            label: data.template.template_label,
                            description: data.template.template_comment,
                            datasource: data.template.template_datasource,
                            target: data.template.template_target,
                            type: data.template.template_type,
                            subject: data.template.subject,
                            file: {}
                        };
                        this.updateTemplateType();

                        this.selectedModelFile = data.template.template_file_name;
                        this.template.template_attachment_type = data.template.template_attachment_type;
                        if (this.template.type === 'HTML' || this.template.type === 'TXT') {
                            this.template.file.content = data.template.template_content;
                        } else if (this.template.type === 'OFFICE') {
                            this.template.file.format = data.template.template_file_name.split('.').pop();
                            this.template.file.name = data.template.template_file_name;
                            this.getViewTemplateContent();
                        } else if (this.template.target === 'acknowledgementReceipt') {
                            this.template.file.electronic.content = data.template.template_content;

                            if (!this.functionsService.empty(data.template.template_file_name)) {
                                this.template.file.paper.format = data.template.template_file_name.split('.').pop();
                                this.template.file.paper.name = data.template.template_file_name;
                                this.getViewTemplateContent();
                            }
                        }
                        this.options = data.template.options;

                        this.headerService.setHeader(this.translate.instant('lang.templateModification'), this.template.template_label);
                        this.loading = false;
                    });
            }
            if (!this.template.template_attachment_type) {
                this.template.template_attachment_type = 'all';
            }
        });
    }

    getViewTemplateContent() {
        this.http.get(`../rest/templates/${this.template.id}/content`).pipe(
            tap((data: any) => {
                this.templateDocView = this.sanitizer.bypassSecurityTrustResourceUrl('data:application/pdf;base64,' + data.encodedDocument);
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    initMce(selectorId: string) {
        setTimeout(() => {
            tinymce.remove('textarea');
            // LOAD EDITOR TINYMCE for MAIL SIGN
            tinymce.baseURL = '../node_modules/tinymce';
            tinymce.suffix = '.min';
            tinymce.init({
                selector: selectorId,
                convert_urls: false,
                statusbar: false,
                language: this.translate.instant('lang.langISO').replace('-', '_'),
                language_url: `../node_modules/tinymce-i18n/langs/${this.translate.instant('lang.langISO').replace('-', '_')}.js`,
                height: '200',
                plugins: [
                    'autoresize',
                    'code'
                ],
                external_plugins: {
                    'maarch_b64image': '../../src/frontend/plugins/tinymce/maarch_b64image/plugin.min.js'
                },
                menubar: false,
                toolbar: 'undo | bold italic underline | alignleft aligncenter alignright | maarch_b64image | forecolor | code',
                theme_buttons1_add: 'fontselect,fontsizeselect',
                theme_buttons2_add_before: 'cut,copy,paste,pastetext,pasteword,separator,search,replace,separator',
                theme_buttons2_add: 'separator,insertdate,inserttime,preview,separator,forecolor,backcolor',
                theme_buttons3_add_before: 'tablecontrols,separator',
                theme_buttons3_add: 'separator,print,separator,ltr,rtl,separator,fullscreen,separator,insertlayer,moveforward,movebackward,absolut',
                theme_toolbar_align: 'left',
                theme_advanced_toolbar_location: 'top',
                theme_styles: 'Header 1=header1;Header 2=header2;Header 3=header3;Table Row=tableRow1',
                setup: (ed: any) => {
                    ed.on('keyup', (e: any) => {
                        if (this.template.type === 'HTML' && tinymce.get('templateHtml') != null) {
                            this.template.file.content = tinymce.get('templateHtml').getContent();
                        }
                        if (this.template.type === 'OFFICE_HTML' && tinymce.get('templateOfficeHtml') != null) {
                            this.template.file.electronic.content = tinymce.get('templateOfficeHtml').getContent();
                        }
                    });
                    ed.on('change', (e: any) => {
                        if (this.template.type === 'HTML' && tinymce.get('templateHtml') != null) {
                            this.template.file.content = tinymce.get('templateHtml').getContent();
                        }
                        if (this.template.type === 'OFFICE_HTML' && tinymce.get('templateOfficeHtml') != null) {
                            this.template.file.electronic.content = tinymce.get('templateOfficeHtml').getContent();
                        }
                    });
                }

            });
        }, 20);
    }

    setInitialValue(data: any) {
        this.extensionModels = [];
        data.templatesModels.forEach((model: any) => {
            if (this.extensionModels.indexOf(model.fileExt) === -1) {
                this.extensionModels.push(model.fileExt);
            }
        });
        this.defaultTemplatesList = data.templatesModels;

        this.attachmentTypesList = data.attachmentTypes;
        this.datasourcesList = data.datasources;
        this.maarchTree.initData(data.entities.map(ent => ({
            ...ent,
            id : ent.serialId,
        })));
    }

    getBase64Document(buffer: ArrayBuffer) {
        const TYPED_ARRAY = new Uint8Array(buffer);
        const STRING_CHAR = TYPED_ARRAY.reduce((data, byte) => data + String.fromCharCode(byte), '');

        return btoa(STRING_CHAR);
    }

    uploadFileTrigger(fileInput: any) {
        if (fileInput.target.files && fileInput.target.files[0] && this.isExtensionAllowed(fileInput.target.files[0])) {

            const reader = new FileReader();

            if (this.template.target === 'acknowledgementReceipt') {
                this.template.file.paper = {
                    name: '',
                    type: '',
                    content: ''
                };
                this.template.file.paper.name = fileInput.target.files[0].name;
                this.selectedModelFile = this.template.file.paper.name;
                this.template.file.paper.type = fileInput.target.files[0].type;
                this.template.file.paper.format = this.template.file.paper.name.split('.').pop();
            } else {
                this.template.file = {
                    name: '',
                    type: '',
                    content: ''
                };
                this.template.file.name = fileInput.target.files[0].name;
                this.selectedModelFile = this.template.file.name;
                this.template.file.type = fileInput.target.files[0].type;
                this.template.file.format = this.template.file.name.split('.').pop();
            }

            reader.readAsArrayBuffer(fileInput.target.files[0]);

            reader.onload = (value: any) => {
                if (this.template.target === 'acknowledgementReceipt') {
                    this.template.file.paper.content = this.getBase64Document(value.target.result);
                } else {
                    this.template.file.content = this.getBase64Document(value.target.result);
                }

                this.documentImported = true;
                this.getViewTemplateFile();
            };
        }
    }

    isExtensionAllowed(file: any) {
        const fileExtension = file.name.toLowerCase().split('.').pop();

        if (this.allowedExtensions.filter(ext => ext.toLowerCase() === fileExtension.toLowerCase()).length === 0) {
            this.dialog.open(AlertComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: {
                title: this.translate.instant('lang.notAllowedExtension') + ' !',
                msg: this.translate.instant('lang.file') + ' : <b>' + file.name + '</b>, ' + this.translate.instant('lang.type') + ' : <b>' + file.type + '</b><br/><br/><u>' + this.translate.instant('lang.allowedExtensions') + '</u> : <br/>' + this.allowedExtensions.filter((elem: any, index: any, self: any) => index === self.indexOf(elem)).join(', ')
            } });
            return false;
        } else {
            return true;
        }
    }

    editFile() {
        const editorOptions: any = {};
        editorOptions.docUrl = 'rest/onlyOffice/mergedFile';
        if (this.creationMode) {
            if (this.template.target !== 'acknowledgementReceipt') {
                if (!this.functionsService.empty(this.template.file.content)) {
                    editorOptions.objectType = 'encodedResource';
                    editorOptions.objectId = this.template.file.content;
                    editorOptions.extension = this.template.file.format;
                } else {
                    editorOptions.objectType = 'templateCreation';
                    for (const element of this.defaultTemplatesList) {
                        if (this.selectedModelFile === element.fileExt + ': ' + element.fileName) {
                            editorOptions.objectId = element.filePath;
                        }
                    }
                    editorOptions.extension = editorOptions.objectId.toLowerCase().split('.').pop();
                }
            } else if (this.template.target === 'acknowledgementReceipt') {
                if (!this.functionsService.empty(this.template.file.paper.content)) {
                    editorOptions.objectType = 'encodedResource';
                    editorOptions.objectId = this.template.file.paper.content;
                    editorOptions.extension = this.template.file.paper.format;
                } else {
                    editorOptions.objectType = 'templateCreation';
                    for (const element of this.defaultTemplatesList) {
                        if (this.selectedModelFile === element.fileExt + ': ' + element.fileName) {
                            editorOptions.objectId = element.filePath;
                        }
                    }
                    editorOptions.extension = editorOptions.objectId.toLowerCase().split('.').pop();
                }
            }
        } else {
            if (this.template.target !== 'acknowledgementReceipt') {
                if (!this.functionsService.empty(this.template.file.content)) {
                    editorOptions.objectType = 'encodedResource';
                    editorOptions.objectId = this.template.file.content;
                    editorOptions.extension = this.template.file.format;
                } else {
                    editorOptions.objectType = 'templateModification';
                    editorOptions.objectId = this.template.id;
                    editorOptions.extension = this.template.file.name.toLowerCase().split('.').pop();
                }
            } else if (this.template.target === 'acknowledgementReceipt') {
                if (!this.functionsService.empty(this.template.file.paper.content)) {
                    editorOptions.objectType = 'encodedResource';
                    editorOptions.objectId = this.template.file.paper.content;
                    editorOptions.extension = this.template.file.paper.format;
                } else {
                    editorOptions.objectType = 'templateModification';
                    editorOptions.objectId = this.template.id;
                    editorOptions.extension = this.template.file.paper.name.toLowerCase().split('.').pop();
                }
            }
        }

        if (this.headerService.user.preferences.documentEdition === 'java') {
            // WORKAROUND JAVA MODE DOESNT SUPPORT BASE64
            if (this.creationMode) {
                editorOptions.objectId = '';
                editorOptions.objectType = 'templateCreation';
                for (const element of this.defaultTemplatesList) {
                    if (this.selectedModelFile === element.fileExt + ': ' + element.fileName) {
                        editorOptions.objectId = element.filePath;
                    }
                }
                if (this.functionsService.empty(editorOptions.objectId)) {
                    alert(this.translate.instant('lang.canNotEditImportedDocumentWhenJava'));
                    return false;
                }
            } else {
                editorOptions.objectType = 'templateModification';
                editorOptions.objectId = this.template.id;
            }
            editorOptions.authToken = this.authService.getToken();
            this.launchJavaEditor(editorOptions);
        } else if (this.headerService.user.preferences.documentEdition !== 'java') {
            this.launchIntegratedEditor(editorOptions, this.headerService.user.preferences.documentEdition);
        }
    }

    launchJavaEditor(params: any) {
        this.http.post('../rest/jnlp', params).pipe(
            tap((data: any) => {
                window.location.href = '../rest/jnlp/' + data.generatedJnlp;
                this.checkLockFile(data.jnlpUniqueId, params.extension);
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    launchIntegratedEditor(params: any, editor: string) {
        this.dialogRef = this.dialog.open(
            TemplateFileEditorModalComponent,
            {
                autoFocus: false,
                panelClass: 'maarch-full-height-modal',
                minWidth: '80%',
                disableClose: true,
                data: {
                    title: this.template.template_style,
                    editorOptions: params,
                    file: { format: params.extension },
                    editorType: editor
                }
            }
        );
        this.dialogRef.afterClosed().pipe(
            filter((data: string) => !this.functionsService.empty(data)),
            tap((data: any) => {
                if (this.template.target === 'acknowledgementReceipt') {
                    this.template.file.paper.name = this.selectedModelFile;
                    this.template.file.paper.format = params.extension;
                    this.template.file.paper.content = data.content;
                } else {
                    this.template.file.name = this.selectedModelFile;
                    this.template.file.format = params.extension;
                    this.template.file.content = data.content;
                }
                this.getViewTemplateFile();
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getViewTemplateFile() {
        this.http.post('../rest/convertedFile/encodedFile', { encodedFile: this.template.target === 'acknowledgementReceipt' ? this.template.file.paper.content : this.template.file.content, format: this.template.target === 'acknowledgementReceipt' ? this.template.file.paper.format : this.template.file.format }).pipe(
            tap((data: any) => {
                this.templateDocView = this.sanitizer.bypassSecurityTrustResourceUrl('data:application/pdf;base64,' + data.encodedResource);
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    checkLockFile(id: string, extension: string) {
        this.intervalLockFile = setInterval(() => {
            this.http.get('../rest/jnlp/lock/' + id).pipe(
                tap((data: any) => {
                    this.lockFound = data.lockFileFound;
                    if (!this.lockFound) {
                        clearInterval(this.intervalLockFile);
                        this.loadTmpFile(`${data.fileTrunk}.${extension}`);
                    }
                })
            ).subscribe();
        }, 1000);
    }

    loadTmpFile(filenameOnTmp: string) {
        this.http.get(`../rest/convertedFile/${filenameOnTmp}?convert=true`).pipe(
            tap((data: any) => {
                if (this.template.target === 'acknowledgementReceipt') {
                    this.template.file.paper.name = this.selectedModelFile;
                    this.template.file.paper.format = filenameOnTmp.toLowerCase().split('.').pop();
                    this.template.file.paper.content = data.encodedResource;
                } else {
                    this.template.file.name = this.selectedModelFile;
                    this.template.file.format = filenameOnTmp.toLowerCase().split('.').pop();
                    this.template.file.content = data.encodedResource;
                }
                this.templateDocView = this.sanitizer.bypassSecurityTrustResourceUrl('data:application/pdf;base64,' + data.encodedConvertedResource);
            })
        ).subscribe();
    }

    duplicateTemplate() {
        if (!this.lockFound && this.template.target !== 'acknowledgementReceipt') {
            const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.duplicate')} « ${this.template.label} »`, msg: this.translate.instant('lang.confirmAction') } });
            dialogRef.afterClosed().pipe(
                filter((data: string) => data === 'ok'),
                exhaustMap(() => this.http.post('../rest/templates/' + this.template.id + '/duplicate', { 'id': this.template.id })),
                tap((data: any) => {
                    this.notify.success(this.translate.instant('lang.templateDuplicated'));
                    this.router.navigate(['/administration/templates/' + data.id]);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    onSubmit() {

        /*
        if (this.template.type === 'HTML') {
            this.template.template_content = tinymce.get('templateHtml').getContent();

        } else if (this.template.type === 'OFFICE_HTML') {
            this.template.template_content = tinymce.get('templateOfficeHtml').getContent();
        }*/

        if (this.isValidTemplate()) {
            if (this.creationMode) {
                this.http.post('../rest/templates', this.formatTemplate()).pipe(
                    tap((data: any) => {
                        if (data.checkEntities) {
                            this.config = {
                                panelClass: 'maarch-modal',
                                data: {
                                    entitiesList: data.checkEntities,
                                    template_attachment_type: this.template.template_attachment_type
                                }
                            };
                            this.dialog.open(TemplateAdministrationCheckEntitiesModalComponent, this.config);
                        } else {
                            this.router.navigate(['/administration/templates']);
                            this.notify.success(this.translate.instant('lang.templateAdded'));
                        }
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
            } else {
                this.http.put('../rest/templates/' + this.template.id, this.formatTemplate()).pipe(
                    tap((data: any) => {
                        if (!this.functionsService.empty(data) && data.checkEntities) {
                            this.config = {
                                panelClass: 'maarch-modal',
                                data: {
                                    entitiesList: data.checkEntities,
                                    template_attachment_type: this.template.template_attachment_type
                                }
                            };
                            this.dialogRef = this.dialog.open(TemplateAdministrationCheckEntitiesModalComponent, this.config);
                        } else {
                            this.router.navigate(['/administration/templates']);
                            this.notify.success(this.translate.instant('lang.templateUpdated'));
                        }
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
            }
        }

    }

    formatTemplate() {
        const template = { ...this.template };
        template.options = {...this.options };
        template.entities = this.maarchTree.getSelectedNodes().map(ent => ent.entity_id);
        return template;
    }

    isValidTemplate() {
        if (this.template.target === 'acknowledgementReceipt' && this.functionsService.empty(this.template.file.paper.name) && this.functionsService.empty(this.template.file.electronic.content)) {
            alert(this.translate.instant('lang.mustCompleteAR'));
            return false;

        } else if (this.template.target !== 'acknowledgementReceipt' && this.template.type === 'OFFICE' && this.functionsService.empty(this.template.file.name)) {
            alert(this.translate.instant('lang.editModelFirst'));
            return false;
        } else {
            return true;
        }
    }

    isVisibleAttachment(id: string) {
        return this.attachmentTypesList.find((attach: any) => attach.id === id)?.visible;
    }

    displayDatasources(datasource: any) {
        if (datasource.target === 'notification' && this.template.target === 'notifications') {
            return true;
        } else if (datasource.target === 'document' && this.template.target !== 'notifications') {
            return true;
        }
        return false;
    }

    updateTemplateType() {
        this.options = {};
        this.template.file = {
            name: '',
            type: '',
            content: ''
        };

        this.templateDocView = null;
        if (['attachments', 'indexingFile'].indexOf(this.template.target) > -1) {
            this.template.type = 'OFFICE';
            this.availableTypes = ['OFFICE'];
        } else if (['notifications', 'sendmail'].indexOf(this.template.target) > -1) {
            this.template.type = 'HTML';
            this.availableTypes = ['HTML', 'TXT'];
            this.initMce('textarea#templateHtml');
        } else if (this.template.target === 'notes') {
            this.template.type = 'TXT';
            this.availableTypes = ['TXT'];
        } else if (this.template.target === 'acknowledgementReceipt') {
            this.options = {
                acknowledgementReceiptFrom : 'destination'
            };
            this.template.file = {
                electronic: {
                    name: '',
                    type: '',
                    content: ''
                },
                paper: {
                    name: '',
                    type: '',
                    content: ''
                }
            };
            this.template.type = 'OFFICE_HTML';
            this.availableTypes = [];
            this.template.template_attachment_type = '';
            this.initMce('textarea#templateOfficeHtml');
        }
    }

    changeType(ev: any) {
        if (ev.value === 'HTML') {
            this.initMce('textarea#templateHtml');
        } else {
            tinymce.remove('textarea');
        }
        this.template.type = ev.value;
    }

    changeModel() {
        this.template.file = {
            name: '',
            type: '',
            content: ''
        };

        this.template.file = {
            electronic: {
                name: '',
                type: '',
                content: ''
            }
        };

        this.documentImported = false;
    }

    loadTab(event: any) {
        if (event.index === 0) {
            this.initMce('textarea#templateOfficeHtml');
        } else {
            tinymce.remove('textarea');
            if (this.template.template_file_name == null && this.template.template_style == null) {
                this.buttonFileName = this.translate.instant('lang.importFile');
            }
        }
    }

    setOption(id: string, value: string, reset: boolean = false) {
        if (reset) {
            this.options = {};
        }
        this.options[id] = value;
    }

    ngOnDestroy() {
        tinymce.remove('textarea');
        if (this.intervalLockFile) {
            clearInterval(this.intervalLockFile);
        }
    }
}
@Component({
    templateUrl: 'template-administration-checkEntities-modal.component.html',
    styleUrls: ['template-administration-checkEntities-modal.scss']
})
export class TemplateAdministrationCheckEntitiesModalComponent {


    constructor(public http: HttpClient, @Inject(MAT_DIALOG_DATA) public data: any, public dialogRef: MatDialogRef<TemplateAdministrationCheckEntitiesModalComponent>) {
    }
}
