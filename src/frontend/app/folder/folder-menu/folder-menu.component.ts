import { Component, OnInit, Input, Output, EventEmitter, Renderer2 } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { map, tap, catchError, filter, exhaustMap, debounceTime, switchMap, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { MatDialogRef, MatDialog } from '@angular/material/dialog';
import { UntypedFormControl } from '@angular/forms';
import { FoldersService } from '../folders.service';
import { FolderCreateModalComponent } from '../folder-create-modal/folder-create-modal.component';
import { FunctionsService } from '@service/functions.service';

@Component({
    selector: 'app-folder-menu',
    templateUrl: 'folder-menu.component.html',
    styleUrls: ['folder-menu.component.scss'],
})
export class FolderMenuComponent implements OnInit {

    @Input('resIds') resIds: number[];
    @Input('currentFolders') currentFoldersList: any[];

    @Output('refreshFolders') refreshFolders = new EventEmitter<string>();
    @Output('refreshList') refreshList = new EventEmitter<string>();

    foldersList: any[] = [];
    pinnedFolder: boolean = true;

    loading: boolean = true;

    searchTerm: UntypedFormControl = new UntypedFormControl();

    dialogRef: MatDialogRef<any>;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialog: MatDialog,
        private renderer: Renderer2,
        private foldersService: FoldersService,
        private functions: FunctionsService,
    ) { }

    ngOnInit(): void {
        this.searchTerm.valueChanges.pipe(
            debounceTime(300),
            tap((value: any) => {
                if (value.length === 0) {
                    this.pinnedFolder = true;
                    this.getFolders();
                }
            }),
            filter(value => value.length > 2),
            tap(() => this.loading = true),
            // distinctUntilChanged(),
            switchMap(data => this.http.get('../rest/autocomplete/folders', { params: { 'search': data } })),
            tap((data: any) => {
                this.pinnedFolder = false;
                this.foldersList = data.map(
                    (info: any) => ({
                        id: info.id,
                        label: info.idToDisplay
                    })
                );
                this.loading = false;
            }),
            catchError((err) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    initFolderMenu() {
        this.searchTerm.setValue('');
        setTimeout(() => {
            this.renderer.selectRootElement('#searchTerm').focus();
        }, 200);
    }

    getFolders() {
        this.loading = true;
        this.http.get('../rest/pinnedFolders').pipe(
            map((data: any) => data.folders),
            tap((data: any) => {
                this.foldersList = data;
            }),
            finalize(() => this.loading = false),
            catchError((err) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    classifyDocuments(folder: any) {

        this.http.post('../rest/folders/' + folder.id + '/resources', { resources: this.resIds }).pipe(
            tap(() => {
                this.foldersService.getPinnedFolders();
                this.refreshList.emit();
                this.refreshFolders.emit();
                this.notify.success(this.translate.instant('lang.mailClassified'));
            }),
            catchError((err) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    unclassifyDocuments(folder: any) {
        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.unclassifyQuestion') + ' <b>' + this.resIds.length + '</b>&nbsp;' + this.translate.instant('lang.mailsInFolder') + ' ?' } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.request('DELETE', '../rest/folders/' + folder.id + '/resources', { body: { resources: this.resIds } })),
            tap(() => {
                this.notify.success(this.translate.instant('lang.removedFromFolder'));
                this.foldersService.getPinnedFolders();
                this.refreshList.emit();
                this.refreshFolders.emit();
            }),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    openCreateFolderModal() {
        this.dialogRef = this.dialog.open(FolderCreateModalComponent, { panelClass: 'maarch-modal', data: { folderName: this.searchTerm.value } });
        this.dialogRef.afterClosed().pipe(
            filter((folderId: number) => !this.functions.empty(folderId)),
            tap((folderId: number) => {
                this.classifyDocuments({ id: folderId });
            }),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
