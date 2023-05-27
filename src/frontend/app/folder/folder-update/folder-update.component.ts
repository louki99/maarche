import { Component, OnInit, Inject, HostListener } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { map, tap, catchError, exhaustMap, finalize } from 'rxjs/operators';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { FunctionsService } from '@service/functions.service';
import { of } from 'rxjs';

declare let $: any;

@Component({
    templateUrl: 'folder-update.component.html',
    styleUrls: ['folder-update.component.scss'],
})
export class FolderUpdateComponent implements OnInit {

    folder: any = {
        id: 0,
        label: '',
        public: true,
        user_id: 0,
        parent_id: null,
        level: 0,
        sharing: {
            entities: []
        }
    };

    sharingFolderCLone: any[] = [];

    holdShift: boolean = false;

    entities: any[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialogRef: MatDialogRef<FolderUpdateComponent>,
        public functions: FunctionsService,
        @Inject(MAT_DIALOG_DATA) public data: any
    ) { }

    @HostListener('document:keydown.Shift', ['$event']) onKeydownHandler(event: KeyboardEvent) {
        this.holdShift = true;
    }
    @HostListener('document:keyup', ['$event']) onKeyupHandler(event: KeyboardEvent) {
        this.holdShift = false;
    }

    ngOnInit(): void {
        this.getFolder();
    }

    getFolder() {
        this.http.get('../rest/folders/' + this.data.folderId).pipe(
            tap((data: any) => this.folder = data.folder),
            exhaustMap(() => this.http.get('../rest/entities')),
            map((data: any) => {
                const keywordEntities = {
                    serialId: 'ALL_ENTITIES',
                    keyword: 'ALL_ENTITIES',
                    parent: '#',
                    icon: 'fa fa-hashtag',
                    allowed: true,
                    text: this.translate.instant('lang.allEntities'),
                    state: { 'opened': false, 'selected': false },
                    parent_entity_id: '',
                    id: 'ALL_ENTITIES',
                    entity_label: this.translate.instant('lang.allEntities')
                };
                data.entities.unshift(keywordEntities);

                this.entities = data.entities;
                data.entities.forEach((element: any) => {
                    if (this.folder.sharing.entities.map((entity: any) => entity.entity_id).indexOf(element.serialId) > -1
                        || this.folder.sharing.entities.map((entity: any) => entity.keyword).indexOf(element.serialId) > -1) {
                        element.state.selected = true;
                    }
                    element.state.allowed = true;
                    element.state.disabled = false;
                });
                return data;
            }),
            tap((data: any) => {
                this.initEntitiesTree(data.entities);
            }),
            exhaustMap(() => this.http.get('../rest/folders')),
            map((data: any) => {
                let currentParentId = 0;
                data.folders.forEach((element: any) => {
                    element['state'] = {
                        opened: true
                    };
                    if (element.parent_id === null) {
                        element.parent_id = '#';
                    }

                    if (element.id === this.folder.parent_id) {
                        element['state'].selected = true;
                    }

                    if (element.id === this.folder.id || currentParentId === element.parent_id || !element.canEdit) {
                        currentParentId = element.id;
                        element['state'].opened = false;
                        element['state'].disabled = true;
                    }
                    element.parent = element.parent_id;
                    element.text = element.label;
                });
                return data;
            }),
            tap((data: any) => {

                this.initFoldersTree(data.folders);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            }),
            finalize(() => this.sharingFolderCLone = JSON.parse(JSON.stringify(this.folder.sharing.entities)))
        ).subscribe();
    }

    initFoldersTree(folders: any) {
        $('#jstreeFolders').jstree({
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
                'data': folders
            },
            'plugins': ['checkbox', 'search']
        });
        $('#jstreeFolders')
            // listen for event
            .on('select_node.jstree', (e: any, data: any) => {
                this.folder.parent_id = data.node.original.id;

            }).on('deselect_node.jstree', (e: any, data: any) => {
                this.folder.parent_id = '';
            })
            // create the instance
            .jstree();
        let to: any = false;
        $('#jstree_searchFolders').keyup(function () {
            if (to) {
                clearTimeout(to);
            }
            to = setTimeout(function () {
                const v: any = $('#jstree_searchFolders').val();
                $('#jstreeFolders').jstree(true).search(v);
            }, 250);
        });
    }

    initEntitiesTree(entities: any) {
        $('#jstree').jstree({
            'checkbox': {
                'three_state': false // no cascade selection
            },
            'core': {
                force_text: true,
                'themes': {
                    'name': 'proton',
                    'responsive': true
                },
                'data': entities
            },
            'plugins': ['checkbox', 'search']
        });
        $('#jstree')
            // listen for event
            .on('select_node.jstree', (e: any, data: any) => {
                this.selectEntity(data.node.original);

            }).on('deselect_node.jstree', (e: any, data: any) => {
                this.deselectEntity(data.node.original);
            })
            // create the instance
            .jstree();
        let to: any = false;
        $('#jstree_search').keyup(function () {
            if (to) {
                clearTimeout(to);
            }
            to = setTimeout(function () {
                const v: any = $('#jstree_search').val();
                $('#jstree').jstree(true).search(v);
            }, 250);
        });
    }

    selectEntity(newEntity: any) {
        if (this.holdShift) {
            $('#jstree').jstree('deselect_all');
            this.folder.sharing.entities = [];
        } else {
            if (!this.functions.empty(newEntity.keyword)) {
                this.folder.sharing.entities.push(
                    {
                        keyword: newEntity.keyword,
                        edition: false
                    }
                );
            } else {
                this.folder.sharing.entities.push(
                    {
                        entity_id: newEntity.serialId,
                        edition: false
                    }
                );
            }
        }
    }

    deselectEntity(entity: any) {
        let index = this.folder.sharing.entities.map((data: any) => data.entity_id).indexOf(entity.serialId);
        if (index > -1) {
            this.folder.sharing.entities.splice(index, 1);
        } else {
            index = this.folder.sharing.entities.map((data: any) => data.keyword).indexOf(entity.serialId);
            if (index > -1) {
                this.folder.sharing.entities.splice(index, 1);
            }
        }
    }

    onSubmit(): void {
        this.http.put('../rest/folders/' + this.folder.id, this.folder).pipe(
            exhaustMap(() => {
                if (JSON.stringify(this.sharingFolderCLone) !== JSON.stringify(this.folder.sharing.entities)) {
                    return this.http.put('../rest/folders/' + this.folder.id + '/sharing', { public: this.folder.sharing.entities.length > 0, sharing: this.folder.sharing });
                } else {
                    return of(false);
                }
            }),
            tap(() => {
                this.notify.success(this.translate.instant('lang.folderUpdated'));
                this.dialogRef.close('success');
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    checkSelectedFolder(entity: any) {
        if (this.folder.sharing.entities.map((data: any) => data.entity_id).indexOf(entity.serialId) > -1
            || this.folder.sharing.entities.map((data: any) => data.keyword).indexOf(entity.serialId) > -1) {
            return true;
        } else {
            return false;
        }
    }

    initService(ev: any) {
        if (ev.index === 1) {
            this.initEntitiesTree(this.entities);
        }
    }

    toggleAdmin(entity: any, ev: any) {
        let index = this.folder.sharing.entities.map((data: any) => data.entity_id).indexOf(entity.serialId);
        if (index > -1) {
            this.folder.sharing.entities[index].edition = ev.checked;
        } else {
            index = this.folder.sharing.entities.map((data: any) => data.keyword).indexOf(entity.serialId);
            if (index > -1) {
                this.folder.sharing.entities[index].edition = ev.checked;
            }
        }
    }

    isAdminEnabled(entity: any) {
        let index = this.folder.sharing.entities.map((data: any) => data.entity_id).indexOf(entity.serialId);
        if (index > -1) {
            return this.folder.sharing.entities[index].edition;
        } else {
            index = this.folder.sharing.entities.map((data: any) => data.keyword).indexOf(entity.serialId);
            if (index > -1) {
                return this.folder.sharing.entities[index].edition;
            }
        }

        return false;
    }
}
