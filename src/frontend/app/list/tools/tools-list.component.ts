import { Component, OnInit, Input, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatAutocompleteTrigger } from '@angular/material/autocomplete';
import { MatDialog } from '@angular/material/dialog';
import { Observable } from 'rxjs';
import { ExportComponent } from '../export/export.component';
import { SummarySheetComponent } from '../summarySheet/summary-sheet.component';
import { PrintedFolderModalComponent } from '@appRoot/printedFolder/printed-folder-modal.component';


export interface StateGroup {
    letter: string;
    names: any[];
}

@Component({
    selector: 'app-tools-list',
    templateUrl: 'tools-list.component.html',
    styleUrls: ['tools-list.component.scss'],
})
export class ToolsListComponent implements OnInit {

    @ViewChild(MatAutocompleteTrigger, { static: true }) autocomplete: MatAutocompleteTrigger;

    @Input() listProperties: any;
    @Input() currentBasketInfo: any;

    @Input() selectedRes: any;
    @Input() totalRes: number;

    priorities: any[] = [];
    categories: any[] = [];
    entitiesList: any[] = [];
    statuses: any[] = [];
    metaSearchInput: string = '';

    stateGroups: StateGroup[] = [];
    stateGroupOptions: Observable<StateGroup[]>;

    isLoading: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialog: MatDialog
    ) { }

    ngOnInit(): void { }

    openExport(): void {
        this.dialog.open(ExportComponent, {
            panelClass: 'maarch-modal',
            width: '800px',
            data: {
                selectedRes: this.selectedRes
            }
        });
    }

    openSummarySheet(): void {
        this.dialog.open(SummarySheetComponent, {
            panelClass: 'maarch-full-height-modal',
            width: '800px',
            data: {
                selectedRes: this.selectedRes
            }
        });
    }

    openPrintedFolderPrompt() {
        this.dialog.open(
            PrintedFolderModalComponent, {
                panelClass: 'maarch-modal',
                width: '800px',
                data: {
                    resId: this.selectedRes,
                    multiple: this.selectedRes.length > 1
                }
            });
    }
}
