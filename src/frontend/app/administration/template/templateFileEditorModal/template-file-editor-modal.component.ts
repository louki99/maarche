import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { EcplOnlyofficeViewerComponent } from '@plugins/onlyoffice-api-js/onlyoffice-viewer.component';
import { CollaboraOnlineViewerComponent } from '@plugins/collabora-online/collabora-online-viewer.component';
import { take, tap } from 'rxjs/operators';
import { Office365SharepointViewerComponent } from '@plugins/office365-sharepoint/office365-sharepoint-viewer.component';

@Component({
    templateUrl: 'template-file-editor-modal.component.html',
    styleUrls: ['template-file-editor-modal.component.scss'],
})
export class TemplateFileEditorModalComponent implements OnInit {

    @ViewChild('onlyofficeViewer', { static: false }) onlyofficeViewer: EcplOnlyofficeViewerComponent;
    @ViewChild('collaboraOnlineViewer', { static: false }) collaboraOnlineViewer: CollaboraOnlineViewerComponent;
    @ViewChild('officeSharepointViewer', { static: false }) officeSharepointViewer: Office365SharepointViewerComponent;

    loading: boolean = false;
    editorOptions: any = null;
    file: any = null;
    editorType: any = null;
    documentIsModified: boolean = false;

    constructor(public translate: TranslateService, public dialogRef: MatDialogRef<TemplateFileEditorModalComponent>, @Inject(MAT_DIALOG_DATA) public data: any) { }

    ngOnInit(): void {
        this.editorOptions = this.data.editorOptions;
        this.file = this.data.file;
        this.editorType = this.data.editorType;
    }

    close() {
        this.loading = true;
        if (this.editorType === 'onlyoffice') {
            this.onlyofficeViewer.getFile().pipe(
                take(1),
                tap((data: any) => {
                    this.loading = false;
                    this.dialogRef.close(data);
                })
            ).subscribe();
        } else if (this.editorType === 'collaboraonline') {
            this.collaboraOnlineViewer.getFile().pipe(
                take(1),
                tap((data: any) => {
                    this.loading = false;
                    this.dialogRef.close(data);
                })
            ).subscribe();
        } else if (this.editorType === 'office365sharepoint') {
            this.officeSharepointViewer.getFile().pipe(
                take(1),
                tap((data: any) => {
                    this.loading = false;
                    this.dialogRef.close(data);
                })
            ).subscribe();
        } else {
            this.loading = false;
            this.dialogRef.close();
        }
    }
}
