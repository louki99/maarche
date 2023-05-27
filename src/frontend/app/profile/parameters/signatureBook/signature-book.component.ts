import { Component, Input, NgZone, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { TranslateService } from '@ngx-translate/core';
import { FunctionsService } from '@service/functions.service';
import { HeaderService } from '@service/header.service';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { ExternalSignatoryBookManagerService } from '@service/externalSignatoryBook/external-signatory-book-manager.service';

@Component({
    selector: 'app-signature-book',
    templateUrl: './signature-book.component.html',
    styleUrls: ['./signature-book.component.scss'],
    providers: [ExternalSignatoryBookManagerService]
})

export class MySignatureBookComponent implements OnInit {

    @Input() signatureModel: any;
    @Input() userSignatures: any[];
    @Input() externalIdMaarchParapheur: any;
    @Input() loadingSign: boolean;

    highlightMe: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public functionsService: FunctionsService,
        public headerService: HeaderService,
        public dialog: MatDialog,
        public externalSignatoryBook: ExternalSignatoryBookManagerService,
        private zone: NgZone,
        private notify: NotificationService
    ){
        window['angularProfileComponent'] = {
            componentAfterUpload: (base64Content: any) => this.processAfterUpload(base64Content),
        };
    }

    ngOnInit(): void {}

    clickOnUploader(id: string) {
        $('#' + id).click();
    }

    processAfterUpload(b64Content: any) {
        this.zone.run(() => this.resfreshUpload(b64Content));
    }

    resfreshUpload(b64Content: any) {
        if (this.signatureModel.size <= 2000000) {
            this.signatureModel.base64 = b64Content.replace(/^data:.*?;base64,/, '');
            this.signatureModel.base64ForJs = b64Content;
        } else {
            this.signatureModel.name = '';
            this.signatureModel.size = 0;
            this.signatureModel.type = '';
            this.signatureModel.base64 = '';
            this.signatureModel.base64ForJs = '';

            this.notify.error('Taille maximum de fichier dépassée (2 MB)');
        }
    }

    uploadSignatureTrigger(fileInput: any) {
        console.log(this.signatureModel);
        if (fileInput.target.files && fileInput.target.files[0]) {
            const reader = new FileReader();

            this.signatureModel.name = fileInput.target.files[0].name;
            this.signatureModel.size = fileInput.target.files[0].size;
            this.signatureModel.type = fileInput.target.files[0].type;
            if (this.signatureModel.label == '') {
                this.signatureModel.label = this.signatureModel.name;
            }

            reader.readAsDataURL(fileInput.target.files[0]);

            reader.onload = (value: any) => {
                window['angularProfileComponent'].componentAfterUpload(value.target.result);
                this.submitSignature();
            };

        }
    }

    dndUploadSignature(event: any) {
        if (event.mouseEvent.dataTransfer.files && event.mouseEvent.dataTransfer.files[0]) {
            const reader = new FileReader();

            this.signatureModel.name = event.mouseEvent.dataTransfer.files[0].name;
            this.signatureModel.size = event.mouseEvent.dataTransfer.files[0].size;
            this.signatureModel.type = event.mouseEvent.dataTransfer.files[0].type;
            if (this.signatureModel.label == '') {
                this.signatureModel.label = this.signatureModel.name;
            }

            reader.readAsDataURL(event.mouseEvent.dataTransfer.files[0]);

            reader.onload = (value: any) => {
                window['angularProfileComponent'].componentAfterUpload(value.target.result);
                this.submitSignature();
            };
        }
    }

    submitSignature() {
        this.http.post('../rest/users/' + this.headerService.user.id + '/signatures', this.signatureModel)
            .subscribe((data: any) => {
                this.userSignatures = data.signatures;
                this.signatureModel = {
                    base64: '',
                    base64ForJs: '',
                    name: '',
                    type: '',
                    size: 0,
                    label: '',
                };
                this.notify.success(this.translate.instant('lang.signatureAdded'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    updateSignature(signature: any) {
        this.http.put('../rest/users/' + this.headerService.user.id + '/signatures/' + signature.id, { 'label': signature.signature_label })
            .subscribe((data: any) => {
                this.notify.success(this.translate.instant('lang.signatureUpdated'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    deleteSignature(id: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.confirmDeleteSignature')}`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/users/' + this.headerService.user.id + '/signatures/' + id)),
            tap((data: any) => {
                this.headerService.user.signatures = data.signatures;
                this.userSignatures = data.signatures;
                this.notify.success(this.translate.instant('lang.signatureDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    async synchronizeSignatures() {
        this.loadingSign = true;
        await this.externalSignatoryBook.synchronizeSignatures(this.headerService.user).finally(() => this.loadingSign = false);
    }

}
