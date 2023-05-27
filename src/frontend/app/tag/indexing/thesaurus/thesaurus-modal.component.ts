import { Component, OnInit, Inject } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';
import { catchError, tap } from 'rxjs/operators';

@Component({
    templateUrl: 'thesaurus-modal.component.html',
    styleUrls: ['thesaurus-modal.component.scss'],
})
export class ThesaurusModalComponent implements OnInit {

    loading: boolean = false;

    tags: any[] = [];

    tag: any = null;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialogRef: MatDialogRef<ThesaurusModalComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public functionsService: FunctionsService
    ) { }

    ngOnInit(): void {
        this.getTagsTree();
    }


    getTags() {
        return new Promise((resolve) => {
            this.http.get('../rest/tags').pipe(
                tap((data: any) => {
                    this.tags = data.tags.map((tag: any) => ({
                        id: tag.id,
                        label: tag.label,
                        parentId: tag.parentId,
                        countResources: tag.countResources
                    }));
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getTag(id: any) {
        this.http.get(`../rest/tags/${id}`).pipe(
            tap((data: any) => {
                this.tag = data;
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    async getTagsTree() {
        await this.getTags();

        const tagsTree = this.tags.map((tag: any) => ({
            id: tag.id,
            text: tag.label,
            parent: this.functionsService.empty(tag.parentId) ? '#' : tag.parentId,
            state: {
                opened: this.data.id == tag.id,
                selected: this.data.id == tag.id,
                disabled: !this.functionsService.empty(this.data.id)
            }
        }));

        setTimeout(() => {
            $('#jstree')
                .on('select_node.jstree', (e: any, item: any) => {
                    this.getTag(item.node.id);
                    // this.tag.parentId.setValue(parseInt(item.node.id));
                })
                .jstree({
                    'checkbox': {
                        'deselect_all': true,
                        'three_state': false // no cascade selection
                    },
                    'core': {
                        force_text: true,
                        'themes': {
                            'name': 'proton',
                            'responsive': true
                        },
                        'multiple': false,
                        'data': tagsTree
                    },
                    'plugins': ['checkbox', 'search', 'sort']
                });
        }, 0);

        if (!this.functionsService.empty(this.data.id)) {
            this.getTag(this.data.id);
        }
    }

    selectTag(id: any) {
        if (this.functionsService.empty(this.data.id)) {
            $('#jstree').jstree('deselect_all');
            $('#jstree').jstree('select_node', id);
        }
    }

    getTagLabel(id: any) {
        return this.tags.filter((tag: any) => tag.id == id)[0].label;
    }

    onSubmit() {
        this.dialogRef.close(this.tag);
    }
}
