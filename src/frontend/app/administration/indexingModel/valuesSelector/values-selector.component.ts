import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { FunctionsService } from '@service/functions.service';

@Component({
    templateUrl: 'values-selector.component.html',
    styleUrls: ['values-selector.component.scss']
})
export class IndexingModelValuesSelectorComponent implements OnInit {

    loading: boolean = true;
    values: any[] = [];

    constructor(
        public translate: TranslateService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<IndexingModelValuesSelectorComponent>,
        public http: HttpClient,
        public functionsServce: FunctionsService
    ) { }

    ngOnInit() {
        this.values = JSON.parse(JSON.stringify(this.data.values));
        this.loading = false;
    }

    onSubmit() {
        this.dialogRef.close (
            {   values: this.values,
                allDoctypes: this.data.allDoctypes
            });
    }

    allChecked() {
        return this.values.filter((val: any) => !val.isTitle).every((el: any) => !el.disabled);
    }

    emptyChecked() {
        return this.values.filter((val: any) => !val.isTitle && !val.disabled).length === 0;
    }

    toggleAll(state: boolean) {
        this.values.filter((item: any) => !item.isTitle).forEach((item: any) => {
            item.disabled = !state;
        });
    }

    getSecondLevel(firstLevelId: number) {
        return this.values.filter((item: any) => item.isTitle && item.firstLevelId === firstLevelId);
    }

    getTypes(secondLevelId: number) {
        return this.values.filter((item: any) => !item.isTitle && item.secondLevelId === secondLevelId);
    }

    allSecondLevel(secondLevelId: number) {
        const secondLevel: any[] = this.values.filter((item: any) => !item.isTitle && item.secondLevelId === secondLevelId);
        return this.values.filter((item: any) => !item.isTitle && item.secondLevelId === secondLevelId && !item.disabled).length === secondLevel.length;
    }

    emptyCheckedSecondLevel(secondLevelId: number) {
        return this.values.filter((item: any) => !item.isTitle && item.secondLevelId === secondLevelId && !item.disabled).length === 0;
    }

    toggleSecondLevel(secondLevelId: number, state: boolean) {
        this.values.filter((item: any) => !item.isTitle && item.secondLevelId === secondLevelId).forEach((element: any) => {
            element.disabled = !state;
        });
    }
}
