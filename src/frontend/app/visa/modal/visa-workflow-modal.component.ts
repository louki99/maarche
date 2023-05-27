import { Component, Inject, OnInit, ViewChild } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { ExternalVisaWorkflowComponent } from '../externalVisaWorkflow/external-visa-workflow.component';


@Component({
    templateUrl: 'visa-workflow-modal.component.html',
    styleUrls: ['visa-workflow-modal.component.scss'],
})
export class VisaWorkflowModalComponent implements OnInit {

    @ViewChild('appExternalVisaWorkflow', { static: true }) appExternalVisaWorkflow: ExternalVisaWorkflowComponent;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<VisaWorkflowModalComponent>
    ) { }

    ngOnInit(): void {
        this.appExternalVisaWorkflow.loadExternalWorkflow(this.data.id, this.data.type);
    }

}
