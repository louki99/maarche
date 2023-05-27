import { NgModule } from '@angular/core';
import { SharedModule } from '../app-common.module';

import { NotesListComponent } from './notes-list.component';
import { NoteEditorComponent } from '../notes/note-editor.component';

@NgModule({
    imports: [
        SharedModule
    ],
    declarations: [
        NotesListComponent,
        NoteEditorComponent
    ],
    exports: [
        NotesListComponent,
        NoteEditorComponent
    ],
    entryComponents: [
    ],
    providers: [

    ],
})
export class NoteModule { }
