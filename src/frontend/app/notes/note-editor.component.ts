import { Component, Input, EventEmitter, Output, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { catchError, tap, debounceTime, filter } from 'rxjs/operators';
import { HeaderService } from '@service/header.service';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';
import { UntypedFormControl } from '@angular/forms';
import { LatinisePipe } from 'ngx-pipes';

@Component({
    selector: 'app-note-editor',
    templateUrl: 'note-editor.component.html',
    styleUrls: ['note-editor.component.scss'],
})
export class NoteEditorComponent implements OnInit {

    @Input() title: string = this.translate.instant('lang.addNote');
    @Input() content: string = '';
    @Input() resIds: any[];
    @Input() addMode: boolean;
    @Input() upMode: boolean;
    @Input() noteContent: string;
    @Input() entitiesNoteRestriction: string[];
    @Input() noteId: number;
    @Input() defaultRestriction: boolean;
    @Input() disableRestriction: boolean = false;
    @Output() refreshNotes = new EventEmitter<string>();

    notes: any;
    loading: boolean = false;
    templatesNote: any = [];
    entities: any = [];

    entitiesRestriction: string[] = [];


    searchTerm: UntypedFormControl = new UntypedFormControl();
    entitiesList: any[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public headerService: HeaderService,
        public functions: FunctionsService,
        private latinisePipe: LatinisePipe) { }

    async ngOnInit() {
        await this.getEntities();

        if (this.defaultRestriction) {
            this.setDefaultRestriction();
        }

        if (this.upMode) {
            this.content = this.noteContent;
            if (this.content.startsWith(`[${this.translate.instant('lang.opinionUserState')}]`) || this.content.startsWith(`[${this.translate.instant('lang.avisUserAsk').toUpperCase()}]`)) {
                this.disableRestriction = true;
            }
            this.entitiesRestriction = this.entitiesNoteRestriction;
        }

        this.entitiesList = this.entities;

        this.searchTerm.valueChanges.pipe(
            debounceTime(300),
            // distinctUntilChanged(),
            tap((data: any) => {
                if (data.length > 0) {
                    const filterValue = this.latinisePipe.transform(data.toLowerCase());
                    this.entitiesList = this.entities.filter( (item: any) => (
                        this.latinisePipe.transform(item.entity_label.toLowerCase()).includes(filterValue)
                                || this.latinisePipe.transform(item.entity_id.toLowerCase()).includes(filterValue)
                    ));
                } else {
                    this.entitiesList = this.entities;
                }
            }),
            catchError((err) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    setDefaultRestriction() {
        this.entitiesRestriction = [];
        this.http.get(`../rest/resources/${this.resIds[0]}/fields/destination`).pipe(
            tap((data: any) => {
                this.entitiesRestriction = this.headerService.user.entities.map((entity: any) => entity.entity_id);
                if (this.entitiesRestriction.indexOf(data.field) === -1 && !this.functions.empty(data.field)) {
                    this.entitiesRestriction.push(data.field);
                }
                this.entities.filter((entity: any) => this.entitiesRestriction.indexOf(entity.id) > -1).forEach((element: any) => {
                    element.selected = true;
                });
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    addNote() {
        this.loading = true;
        this.http.post('../rest/notes', { value: this.content, resId: this.resIds[0], entities: this.entitiesRestriction })
            .subscribe((data: any) => {
                this.refreshNotes.emit(this.resIds[0]);
                this.loading = false;
            });
    }

    updateNote() {
        this.loading = true;
        this.http.put('../rest/notes/' + this.noteId, { value: this.content, resId: this.resIds[0], entities: this.entitiesRestriction })
            .subscribe((data: any) => {
                this.refreshNotes.emit(this.resIds[0]);
                this.loading = false;
            });
    }

    getNoteContent() {
        return this.content;
    }

    setNoteContent(content: string) {
        this.content = content;
    }


    getNote() {
        return {content: this.content, entities: this.entitiesRestriction};
    }

    selectTemplate(template: any) {
        if (this.content.length > 0) {
            this.content = this.content + ' ' + template.template_content;
        } else {
            this.content = template.template_content;
        }
    }

    selectEntity(entity: any) {
        entity.selected = true;
        this.entitiesRestriction.push(entity.id);
    }

    getTemplatesNote() {
        if (this.templatesNote.length == 0) {
            const params = {};
            if (!this.functions.empty(this.resIds) && this.resIds.length == 1) {
                params['resId'] = this.resIds[0];
            }
            this.http.get('../rest/notesTemplates', { params: params })
                .subscribe((data: any) => {
                    this.templatesNote = data['templates'];
                });

        }
    }

    getEntities() {
        return new Promise((resolve, reject) => {
            if (this.entities.length == 0) {
                const params = {};
                if (!this.functions.empty(this.resIds) && this.resIds.length == 1) {
                    params['resId'] = this.resIds[0];
                }
                this.http.get('../rest/entities').pipe(
                    tap((data: any) => {
                        this.entities = data['entities'];
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        resolve(false);
                        return of(false);
                    })
                ).subscribe();

            }
        });
    }

    removeEntityRestriction(index: number, realIndex: number) {
        this.entities[realIndex].selected = false;
        this.entitiesRestriction.splice(index, 1);
    }

    isWritingNote() {
        return this.content !== '';
    }
}
