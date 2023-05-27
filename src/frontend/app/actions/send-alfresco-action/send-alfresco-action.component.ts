import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, finalize, catchError, debounceTime, filter, switchMap } from 'rxjs/operators';
import { UntypedFormControl } from '@angular/forms';
import { FunctionsService } from '@service/functions.service';
import { MaarchTreeComponent } from '../../../plugins/tree/maarch-tree.component';
import { of } from 'rxjs';
import { SessionStorageService } from '@service/session-storage.service';

declare let $: any;

@Component({
    templateUrl: 'send-alfresco-action.component.html',
    styleUrls: ['send-alfresco-action.component.scss'],
})
export class SendAlfrescoActionComponent implements OnInit {

    @ViewChild('noteEditor', { static: true }) noteEditor: NoteEditorComponent;
    @ViewChild('maarchTree', { static: false }) maarchTree: MaarchTreeComponent;

    loading: boolean = false;

    errors: any;

    alfrescoFolders: any[] = [];

    searchFolder = new UntypedFormControl();

    selectedFolder: number = null;
    selectedFolderName: string = null;

    resourcesErrors: any[] = [];
    noResourceToProcess: boolean = null;

    canGoToNextRes: boolean = false;
    showToggle: boolean = false;
    inLocalStorage: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialogRef: MatDialogRef<SendAlfrescoActionComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public functions: FunctionsService,
        private sessionStorage: SessionStorageService
    ) { }

    async ngOnInit(): Promise<void> {
        this.loading = true;
        this.showToggle = this.data.additionalInfo.showToggle;
        this.canGoToNextRes = this.data.additionalInfo.canGoToNextRes;
        this.inLocalStorage = this.data.additionalInfo.inLocalStorage;
        await this.checkAlfresco();
        this.loading = false;
        this.getRootFolders();

        this.searchFolder.valueChanges
            .pipe(
                debounceTime(300),
                tap(async (value: any) => {
                    this.selectedFolder = null;
                    this.selectedFolderName = null;
                    if (value.length === 0) {
                        await this.getRootFolders();
                        this.refreshTree();
                    }
                }),
                filter(value => value.length > 2),
                switchMap(data => this.http.get('../rest/alfresco/autocomplete/folders', { params: { 'search': data } })),
                tap((data: any) => {
                    this.alfrescoFolders = data;
                    this.refreshTree();
                })
            ).subscribe();
    }

    checkAlfresco() {
        this.resourcesErrors = [];

        return new Promise((resolve, reject) => {
            this.http.post('../rest/resourcesList/users/' + this.data.userId + '/groups/' + this.data.groupId + '/baskets/' + this.data.basketId + '/actions/' + this.data.action.id + '/checkSendAlfresco', { resources: this.data.resIds })
                .pipe(
                    tap((data: any) => {
                        if (!this.functions.empty(data.fatalError)) {
                            this.notify.error(this.translate.instant('lang.' + data.reason));
                            this.dialogRef.close();
                        } else if (!this.functions.empty(data.resourcesInformations.error)) {
                            this.resourcesErrors = data.resourcesInformations.error;
                            this.noResourceToProcess = this.resourcesErrors.length === this.data.resIds.length;
                        }
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        this.dialogRef.close();
                        return of(false);
                    })
                ).subscribe();
        });
    }

    refreshTree() {
        const tmpData = this.alfrescoFolders;
        this.alfrescoFolders = [];

        setTimeout(() => {
            this.alfrescoFolders = tmpData;
        }, 200);
    }

    getRootFolders() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/alfresco/rootFolders').pipe(
                tap((data: any) => {
                    this.alfrescoFolders = data;
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    selectFolder(folder: any) {
        this.selectedFolder = folder.id;
        this.selectedFolderName = this.getNameWithParents(folder.text, folder.parent);
    }

    onSubmit() {
        this.loading = true;

        if (this.data.resIds.length > 0) {
            this.sessionStorage.checkSessionStorage(this.inLocalStorage, this.canGoToNextRes, this.data);
            this.executeAction();
        }
    }

    executeAction() {

        const realResSelected: number[] = this.data.resIds.filter((resId: any) => this.resourcesErrors.map(resErr => resErr.res_id).indexOf(resId) === -1);

        this.http.put(this.data.processActionRoute, { resources: realResSelected, note: this.noteEditor.getNoteContent(), data: { folderId: this.selectedFolder, folderName: this.selectedFolderName } }).pipe(
            tap((data: any) => {
                if (!data) {
                    this.dialogRef.close('success');
                }
                if (data && data.errors != null) {
                    this.notify.error(data.errors);
                }
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isValidAction() {
        return this.selectedFolder !== null && !this.noResourceToProcess;
    }

    getNameWithParents(name: string, parentId: string) {
        if (parentId === '#') {
            return name;
        }

        this.maarchTree.getTreeData().forEach((folder: any) => {
            if (folder.id == parentId) {
                name = folder.text + '/' + name;
                if (folder.parent !== '#') {
                    name = this.getNameWithParents(name, folder.parent);
                }
            }
        });

        return name;
    }
}
