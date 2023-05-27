import { Component, OnInit, ViewChild, EventEmitter, Output } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { DiffusionsListComponent } from '../../diffusions/diffusions-list.component';
import { VisaWorkflowComponent } from '../../visa/visa-workflow.component';
import { AvisWorkflowComponent } from '../../avis/avis-workflow.component';
import { NotesListComponent } from '../../notes/notes-list.component';
import { AttachmentsListComponent } from '../../attachments/attachments-list.component';
import { SentResourceListComponent } from '@appRoot/sentResource/sent-resource-list.component';

declare let $: any;

@Component({
    selector: 'app-panel-list',
    templateUrl: 'panel-list.component.html',
    styleUrls: ['panel-list.component.scss'],
})
export class PanelListComponent implements OnInit {

    @Output() refreshBadgeNotes = new EventEmitter<string>();
    @Output() refreshBadgeAttachments = new EventEmitter<string>();
    @Output() refreshBadgeSentResource = new EventEmitter<string>();

    @ViewChild('appDiffusionsList', { static: false }) appDiffusionsList: DiffusionsListComponent;
    @ViewChild('appVisaWorkflow', { static: false }) appVisaWorkflow: VisaWorkflowComponent;
    @ViewChild('appAvisWorkflow', { static: false }) appAvisWorkflow: AvisWorkflowComponent;
    @ViewChild('appNotesList', { static: false }) appNotesList: NotesListComponent;
    @ViewChild('appAttachmentsList', { static: false }) appAttachmentsList: AttachmentsListComponent;
    @ViewChild('sentResourceListComponent', { static: false }) sentResourceListComponent: SentResourceListComponent;

    loading: boolean = false;

    selectedDiffusionTab: number = 0;
    injectDatasParam = {
        resId: 0,
        editable: false
    };

    mode: string;
    icon: string;
    currentResource: any = {};

    constructor(public translate: TranslateService) { }

    ngOnInit(): void { }

    loadComponent(mode: string, data: any) {

        this.mode = mode;
        this.currentResource = data;

        this.injectDatasParam.resId = this.currentResource.resId;

        if (mode === 'diffusion') {
            setTimeout(() => {
                this.icon = 'fa-sitemap';
                this.selectedDiffusionTab = 0;
                this.injectDatasParam.resId = this.currentResource.resId;
                this.appDiffusionsList.loadListinstance(this.currentResource.resId);
                this.appVisaWorkflow.loadWorkflow(this.currentResource.resId);
                this.appAvisWorkflow.loadWorkflow(this.currentResource.resId);
            }, 0);

        } else if (mode === 'note') {
            setTimeout(() => {
                this.icon = 'fa-comments';
                this.appNotesList.loadNotes(this.currentResource.resId);
            }, 0);

            setTimeout(() => {
                $('textarea').focus();
            }, 200);
        } else if (mode === 'attachment') {
            setTimeout(() => {
                this.icon = 'fa-paperclip';
                this.appAttachmentsList.loadAttachments(this.currentResource.resId);
            }, 0);
        } else if (mode === 'sentResources') {
            this.mode = '';
            setTimeout(() => {
                this.mode = 'sentResources';
                this.icon = 'fa-envelope';
            }, 0);
        }
    }

    reloadBadgeNotes(nb: any) {
        this.refreshBadgeNotes.emit(nb);
    }

    reloadBadgeAttachments(nb: any) {
        this.refreshBadgeAttachments.emit(nb);
    }

    reloadBadgeSentResources(nb: any) {
        this.refreshBadgeSentResource.emit(nb);
    }
}
