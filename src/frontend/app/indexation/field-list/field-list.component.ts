import { Component, OnInit, Input } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatDialog } from '@angular/material/dialog';
import { AppService } from '@service/app.service';
import { SortPipe } from '../../../plugins/sorting.pipe';
import { moveItemInArray, CdkDragDrop, transferArrayItem } from '@angular/cdk/drag-drop';

@Component({
    selector: 'app-field-list',
    templateUrl: 'field-list.component.html',
    styleUrls: [
        'field-list.component.scss',
        '../../indexation/indexing-form/indexing-form.component.scss'
    ],
    providers: [SortPipe]
})

export class FieldListComponent implements OnInit {

    @Input() dataCustomFields: any[];

    @Input() dataFields: any[];

    loading: boolean = false;


    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialog: MatDialog,
        public appService: AppService,
    ) { }

    ngOnInit(): void { }

    drop(event: CdkDragDrop<string[]>) {
        event.item.data.unit = event.container.id.split('_')[1];

        if (event.previousContainer === event.container) {
            moveItemInArray(event.container.data, event.previousIndex, event.currentIndex);
        } else {
            transferArrayItem(event.previousContainer.data,
                event.container.data,
                event.previousIndex,
                event.currentIndex);
        }
    }
}
