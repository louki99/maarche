import { Component, Input, OnInit, EventEmitter, Output, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { tap, finalize, catchError, exhaustMap, filter } from 'rxjs/operators';
import { of } from 'rxjs';
import { HeaderService } from '@service/header.service';
import { ConfirmComponent } from '../../plugins/modal/confirm.component';
import { MatDialogRef, MatDialog } from '@angular/material/dialog';
import { FunctionsService } from '@service/functions.service';
import { NoteEditorComponent } from './note-editor.component';

@Component({
    selector: 'app-notes-list',
    templateUrl: 'notes-list.component.html',
    styleUrls: ['notes-list.component.scss'],
})
export class NotesListComponent implements OnInit {

    @Input() injectDatas: any;

    @Input() resId: number = null;
    @Input() editMode: boolean = false;

    @Output() reloadBadgeNotes = new EventEmitter<string>();

    @ViewChild('noteEditor', { static: false }) noteEditor: NoteEditorComponent;
    notes: any[] = [];
    loading: boolean = true;
    resIds: number[] = [];


    dialogRef: MatDialogRef<any>;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public headerService: HeaderService,
        public dialog: MatDialog,
        public functions: FunctionsService
    ) { }

    ngOnInit(): void {
        if (this.resId !== null) {
            this.http.get(`../rest/resources/${this.resId}/notes`).pipe(
                tap((data: any) => {
                    this.resIds[0] = this.resId;
                    this.notes = data['notes'];
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    loadNotes(resId: number) {
        this.resIds[0] = resId;
        this.loading = true;
        this.http.get('../rest/resources/' + this.resIds[0] + '/notes')
            .subscribe((data: any) => {
                this.notes = data['notes'];
                this.reloadBadgeNotes.emit(`${this.notes.length}`);
                this.loading = false;
            });
    }

    getRestrictionEntitiesId(entities: any) {
        if (!this.functions.empty(entities)) {
            return entities.map((entity: any) => entity.item_id[0]);
        }
        return [];
    }

    removeNote(note: any) {
        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: false, data: { title: this.translate.instant('lang.confirmRemoveNote'), msg: this.translate.instant('lang.confirmAction') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.request('DELETE', '../rest/notes/' + note.id)),
            tap(() => {
                const index = this.notes.findIndex(elem => elem.id == note.id);
                if (index > -1) {
                    this.notes.splice(index, 1);
                }
                this.notify.success(this.translate.instant('lang.noteRemoved'));
                this.reloadBadgeNotes.emit(`${this.notes.length}`);
            })
        ).subscribe();
    }

    editNote(note: any) {
        if (!note.edit) {
            note.edit = true;
        } else {
            note.edit = false;
        }
    }

    isModified() {
        return this.noteEditor === undefined ? false :  this.noteEditor.isWritingNote();
    }

    addNote() {
        this.noteEditor.addNote();
    }
}
