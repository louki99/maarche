import { Component, OnInit, Input, Output, EventEmitter } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { map, catchError, filter, exhaustMap, finalize, tap } from 'rxjs/operators';
import { MatDialogRef, MatDialog } from '@angular/material/dialog';
import { ConfirmComponent } from '../../../../plugins/modal/confirm.component';
import { CdkDragDrop, moveItemInArray } from '@angular/cdk/drag-drop';
import { of } from 'rxjs';

declare let $: any;

@Component({
    selector: 'app-indexing-administration',
    templateUrl: 'indexing-administration.component.html',
    styleUrls: ['indexing-administration.component.scss'],
})
export class IndexingAdministrationComponent implements OnInit {

    @Input() groupId: number;
    @Output() resfreshShortcut = new EventEmitter<string>();

    mobileQuery: MediaQueryList;

    loading: boolean = true;

    keywordEntities: any[] = [];
    actionList: any[] = [];

    indexingInfo: any = {
        canIndex: false,
        actions: [],
        keywords: [],
        entities: []
    };
    dialogRef: MatDialogRef<any>;
    emptyField: boolean = true;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private dialog: MatDialog,
    ) {

        this.keywordEntities = [{
            id: 'ALL_ENTITIES',
            keyword: 'ALL_ENTITIES',
            parent: '#',
            icon: 'fa fa-hashtag',
            allowed: true,
            text: this.translate.instant('lang.allEntities')
        }, {
            id: 'ENTITIES_JUST_BELOW',
            keyword: 'ENTITIES_JUST_BELOW',
            parent: '#',
            icon: 'fa fa-hashtag',
            allowed: true,
            text: this.translate.instant('lang.immediatelyBelowMyPrimaryEntity')
        }, {
            id: 'ENTITIES_BELOW',
            keyword: 'ENTITIES_BELOW',
            parent: '#',
            icon: 'fa fa-hashtag',
            allowed: true,
            text: this.translate.instant('lang.belowAllMyEntities')
        }, {
            id: 'ALL_ENTITIES_BELOW',
            keyword: 'ALL_ENTITIES_BELOW',
            parent: '#',
            icon: 'fa fa-hashtag',
            allowed: true,
            text: this.translate.instant('lang.belowMyPrimaryEntity')
        }, {
            id: 'MY_ENTITIES',
            keyword: 'MY_ENTITIES',
            parent: '#',
            icon: 'fa fa-hashtag',
            allowed: true,
            text: this.translate.instant('lang.myEntities')
        }, {
            id: 'MY_PRIMARY_ENTITY',
            keyword: 'MY_PRIMARY_ENTITY',
            parent: '#',
            icon: 'fa fa-hashtag',
            allowed: true,
            text: this.translate.instant('lang.myPrimaryEntity')
        }, {
            id: 'SAME_LEVEL_ENTITIES',
            keyword: 'SAME_LEVEL_ENTITIES',
            parent: '#',
            icon: 'fa fa-hashtag',
            allowed: true,
            text: this.translate.instant('lang.sameLevelMyPrimaryEntity')
        }, {
            id: 'ENTITIES_JUST_UP',
            keyword: 'ENTITIES_JUST_UP',
            parent: '#',
            icon: 'fa fa-hashtag',
            allowed: true,
            text: this.translate.instant('lang.immediatelySuperiorMyPrimaryEntity')
        }];
    }

    ngOnInit(): void {
        this.getIndexingInformations().pipe(
            tap((data: any) => this.indexingInfo.canIndex = data.group.canIndex),
            tap((data: any) => this.getActions(data.actions)),
            tap((data: any) => this.getSelectedActions(data.group.indexationParameters.actions)),
            map((data: any) => this.getEntities(data)),
            map((data: any) => this.getSelectedEntities(data)),
            tap((data: any) => this.initEntitiesTree(data)),
            finalize(() => this.loading = false)
        ).subscribe();
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

        let to: any = false;
        $('#jstree_search').keyup( () => {
            const v: any = $('#jstree_search').val();
            this.emptyField = v === '' ? true : false;
            if (to) {
                clearTimeout(to);
            }
            to = setTimeout(function () {
                $('#jstree').jstree(true).search(v);
            }, 250);
        });


        $('#jstree')
            // listen for event
            .on('select_node.jstree', (e: any, data: any) => {
                if (isNaN(data.node.id)) {
                    this.addKeyword(data.node.id);
                } else {
                    this.addEntity(data.node.id);
                }

            }).on('deselect_node.jstree', (e: any, data: any) => {
                if (isNaN(data.node.id)) {
                    this.removeKeyword(data.node.id);
                } else {
                    this.removeEntity(data.node.id);
                }
            })
            // create the instance
            .jstree();
    }

    getEntities(data: any) {
        this.keywordEntities.forEach((entity: any) => {
            if (data.group.indexationParameters.keywords.indexOf(entity.id) > -1) {
                entity.state = { 'opened': true, 'selected': true };
            } else {
                entity.state = { 'opened': true, 'selected': false };
            }
        });
        data.entities = this.keywordEntities.concat(data.entities);
        return data;
    }

    getSelectedEntities(data: any) {
        this.indexingInfo.entities = [...data.group.indexationParameters.entities];
        this.indexingInfo.keywords = [...data.group.indexationParameters.keywords];
        return data.entities;
    }

    getActions(data: any) {
        this.actionList = data;
    }

    getIndexingInformations() {
        return this.http.get('../rest/groups/' + this.groupId + '/indexing');
    }

    getSelectedActions(data: any) {
        let index = -1;
        data.forEach((actionId: any) => {
            index = this.actionList.findIndex(action => action.id == actionId);
            if (index > -1) {
                this.indexingInfo.actions.push(this.actionList[index]);
                this.actionList.splice(index, 1);
            }
        });
    }

    addEntity(entityId: number) {
        const newEntityList = this.indexingInfo.entities.concat([entityId]);

        this.http.put('../rest/groups/' + this.groupId + '/indexing', { entities: newEntityList }).pipe(
            tap(() => {
                this.indexingInfo.entities.push(entityId);
            }),
            tap(() => {
                this.notify.success(this.translate.instant('lang.entityAdded'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    removeEntity(entityId: number) {
        const index = this.indexingInfo.entities.indexOf(entityId);
        const newEntityList = [...this.indexingInfo.entities];
        newEntityList.splice(index, 1);

        this.http.put('../rest/groups/' + this.groupId + '/indexing', { entities: newEntityList }).pipe(
            tap(() => {
                this.indexingInfo.entities.splice(index, 1);
            }),
            tap(() => {
                this.notify.success(this.translate.instant('lang.entityDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    addKeyword(keyword: string) {
        const newKeywordList = this.indexingInfo.keywords.concat([keyword]);

        this.http.put('../rest/groups/' + this.groupId + '/indexing', { keywords: newKeywordList }).pipe(
            tap(() => {
                this.indexingInfo.keywords.push(keyword);
            }),
            tap(() => {
                this.notify.success(this.translate.instant('lang.keywordAdded'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    removeKeyword(keyword: string) {
        const index = this.indexingInfo.keywords.indexOf(keyword);
        const newKeywordList = [...this.indexingInfo.keywords];
        newKeywordList.splice(index, 1);

        this.http.put('../rest/groups/' + this.groupId + '/indexing', { keywords: newKeywordList }).pipe(
            tap(() => {
                this.indexingInfo.keywords.splice(index, 1);
            }),
            tap(() => {
                this.notify.success(this.translate.instant('lang.keywordDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    addAction(actionOpt: any) {
        const newActionListIds = this.indexingInfo.actions.map((action: any) => action.id).concat([actionOpt].map((action: any) => action.id));

        this.http.put('../rest/groups/' + this.groupId + '/indexing', { actions: newActionListIds }).pipe(
            tap(() => {
                const index = this.actionList.findIndex(item => item.id === actionOpt.id);
                const action = { ...this.actionList[index] };
                this.indexingInfo.actions.push(action);
                this.actionList.splice(index, 1);
            }),
            tap(() => {
                this.notify.success(this.translate.instant('lang.actionAdded'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    removeAction(index: number) {
        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            map(() => {
                this.dialogRef = null;
                const newActionList = [...this.indexingInfo.actions];
                newActionList.splice(index, 1);
                return newActionList.map((action: any) => action.id);
            }),
            exhaustMap((data) => this.http.put('../rest/groups/' + this.groupId + '/indexing', { actions: data })),
            tap(() => {
                this.actionList.push(this.indexingInfo.actions[index]);
                this.indexingInfo.actions.splice(index, 1);
            }),
            tap(() => {
                this.notify.success(this.translate.instant('lang.actionDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    toggleIndex(canIndex: boolean) {

        this.http.put('../rest/groups/' + this.groupId + '/indexing', { canIndex: canIndex }).pipe(
            tap(() => {
                this.indexingInfo.canIndex = canIndex;
                this.resfreshShortcut.emit();
            }),
            tap(() => {
                if (this.indexingInfo.canIndex) {
                    this.notify.success(this.translate.instant('lang.indexEnabled'));
                } else {
                    this.notify.success(this.translate.instant('lang.indexDisabled'));
                }
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }
    drop(event: CdkDragDrop<string[]>) {

        if (event.previousContainer === event.container) {
            moveItemInArray(event.container.data, event.previousIndex, event.currentIndex);
            const newActionListIds = this.indexingInfo.actions.map((action: any) => action.id);

            this.http.put('../rest/groups/' + this.groupId + '/indexing', { actions: newActionListIds }).pipe(
                tap(() => {
                    this.notify.success(this.translate.instant('lang.actionAdded'));
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    clearFilter() {
        $('#jstree_search').val('');
        $('#jstree').jstree(true).search('');
        this.emptyField = true;
    }
}
