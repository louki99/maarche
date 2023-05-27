/* eslint-disable @typescript-eslint/member-ordering */
import { Component, OnInit, ViewChild, Input, Renderer2, Output, EventEmitter, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { tap, catchError, filter, exhaustMap } from 'rxjs/operators';
import { FlatTreeControl } from '@angular/cdk/tree';
import { trigger, transition, style, animate } from '@angular/animations';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { MatInput } from '@angular/material/input';
import { MatTreeFlatDataSource, MatTreeFlattener } from '@angular/material/tree';
import { NotificationService } from '@service/notification/notification.service';
import { ConfirmComponent } from '../../plugins/modal/confirm.component';
import { Router } from '@angular/router';
import { FolderUpdateComponent } from './folder-update/folder-update.component';
import { FoldersService } from './folders.service';
import { UntypedFormControl } from '@angular/forms';
import { PluginAutocompleteComponent } from '../../plugins/autocomplete/autocomplete.component';
import { HeaderService } from '@service/header.service';
import { FolderCreateModalComponent } from './folder-create-modal/folder-create-modal.component';
import { FunctionsService } from '@service/functions.service';
import { BehaviorSubject, of, Subscription } from 'rxjs';

/**
 * Node for to-do item
 */
export class ItemNode {
    id: number;
    children: ItemNode[];
    label: string;
    parent_id: number;
    public: boolean;
    pinned: boolean;
    countResources: number;
}

/** Flat to-do item node with expandable and level information */
export class ItemFlatNode {
    id: number;
    label: string;
    parent_id: number;
    countResources: number;
    level: number;
    public: boolean;
    pinned: boolean;
    expandable: boolean;
}
@Component({
    selector: 'folder-tree',
    templateUrl: 'folder-tree.component.html',
    styleUrls: ['folder-tree.component.scss'],
    animations: [
        trigger('hideShow', [
            transition(
                ':enter', [
                    style({ height: '0px' }),
                    animate('200ms', style({ 'height': '30px' }))
                ]
            ),
            transition(
                ':leave', [
                    style({ height: '30px' }),
                    animate('200ms', style({ 'height': '0px' }))
                ]
            )
        ]),
    ],
})
export class FolderTreeComponent implements OnInit, OnDestroy {

    @ViewChild('itemValue', { static: true }) itemValue: MatInput;
    @ViewChild('autocomplete', { static: false }) autocomplete: PluginAutocompleteComponent;
    @ViewChild('tree', { static: true }) tree: any;

    @Input() selectedId: number;

    @Output() refreshDocList = new EventEmitter<string>();
    @Output() refreshFolderList = new EventEmitter<string>();

    subscription: Subscription;

    /** Map from flat node to nested node. This helps us finding the nested node to be modified */
    flatNodeMap = new Map<ItemFlatNode, ItemNode>();

    /** Map from nested node to flattened node. This helps us to keep the same object for selection */
    nestedNodeMap = new Map<ItemNode, ItemFlatNode>();

    loading: boolean = true;

    searchTerm: UntypedFormControl = new UntypedFormControl();

    TREE_DATA: any[] = [];
    dialogRef: MatDialogRef<any>;
    createRootNode: boolean = false;
    createItemNode: boolean = false;
    dataChange = new BehaviorSubject<ItemNode[]>([]);

    get data(): ItemNode[] {
        return this.dataChange.value;
    }

    private transformer = (node: ItemNode, level: number) => {
        const existingNode = this.nestedNodeMap.get(node);
        const flatNode = existingNode && existingNode.label === node.label
            ? existingNode
            : new ItemFlatNode();
        flatNode.label = node.label;
        flatNode.countResources = node.countResources;
        flatNode.public = node.public;
        flatNode.pinned = node.pinned;
        flatNode.parent_id = node.parent_id;
        flatNode.id = node.id;
        flatNode.level = level;
        flatNode.expandable = !!node.children;
        this.flatNodeMap.set(flatNode, node);
        this.nestedNodeMap.set(node, flatNode);
        return flatNode;
    };

    treeControl = new FlatTreeControl<any>(
        node => node.level, node => node.expandable);

    treeFlattener = new MatTreeFlattener(
        this.transformer, node => node.level, node => node.expandable, node => node.children);

    dataSource = new MatTreeFlatDataSource(this.treeControl, this.treeFlattener);

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private dialog: MatDialog,
        private router: Router,
        private renderer: Renderer2,
        private headerService: HeaderService,
        public foldersService: FoldersService,
        private functions: FunctionsService,
    ) {
        // Event after process action
        this.subscription = this.foldersService.catchEvent().subscribe((result: any) => {
            if (result.type === 'initTree') {
                const folders = this.flatToNestedObject(this.foldersService.getList());
                if (folders.length > 0) {
                    this.initTree(folders);
                    this.openTree(this.foldersService.getCurrentFolder().id);
                }
                this.loading = false;
            } else if (result.type === 'refreshFolderCount') {
                this.treeControl.dataNodes.filter(folder => folder.id === result.content.id)[0].countResources = result.content.countResources;
            } else if (result.type === 'refreshFolderPinned') {
                this.treeControl.dataNodes.filter(folder => folder.id === result.content.id)[0].pinned = result.content.pinned;
            } else {
                if (this.treeControl.dataNodes !== undefined) {
                    this.openTree(this.foldersService.getCurrentFolder().id);
                }
            }
        });
    }

    ngOnInit(): void {
        this.getFolders();
    }

    getFolders() {
        this.loading = true;
        this.foldersService.getFolders();
    }

    initTree(data: any) {
        this.dataChange.next(data);
        this.dataChange.subscribe(info => {
            this.dataSource.data = info;
        });
    }

    openTree(id: any) {
        let indexSelectedFolder = this.treeControl.dataNodes.map((folder: any) => folder.id).indexOf(parseInt(id));

        while (indexSelectedFolder !== -1) {
            indexSelectedFolder = this.treeControl.dataNodes.map((folder: any) => folder.id).indexOf(this.treeControl.dataNodes[indexSelectedFolder].parent_id);
            if (indexSelectedFolder !== -1) {
                this.treeControl.expand(this.treeControl.dataNodes[indexSelectedFolder]);
            }
        }
    }

    hasChild = (_: number, node: any) => node.expandable;

    hasNoContent = (_: number, _nodeData: any) => _nodeData.label === '';

    showAction(node: any) {
        this.treeControl.dataNodes.forEach(element => {
            element.showAction = false;
        });
        node.showAction = true;
    }

    hideAction(node: any) {
        node.showAction = false;
    }

    flatToNestedObject(data: any) {
        const nested = data.reduce((initial: any, value: any, index: any, original: any) => {
            if (value.parent_id === null) {
                if (initial.left.length) {
                    this.checkLeftOvers(initial.left, value);
                }
                delete value.parent_id;
                value.root = true;
                initial.nested.push(value);
            } else {
                const parentFound = this.findParent(initial.nested, value);
                if (parentFound) {
                    this.checkLeftOvers(initial.left, value);
                } else {
                    initial.left.push(value);
                }
            }
            return index < original.length - 1 ? initial : initial.nested;
        }, { nested: [], left: [] });
        return nested;
    }

    checkLeftOvers(leftOvers: any, possibleParent: any) {
        for (let i = 0; i < leftOvers.length; i++) {
            if (leftOvers[i].parent_id === possibleParent.id) {
                // delete leftOvers[i].parent_id;
                possibleParent.children ? possibleParent.children.push(leftOvers[i]) : possibleParent.children = [leftOvers[i]];
                possibleParent.count = possibleParent.children.length;
                const addedObj = leftOvers.splice(i, 1);
                this.checkLeftOvers(leftOvers, addedObj[0]);
            }
        }
    }

    findParent(possibleParents: any, possibleChild: any): any {
        let found = false;
        for (let i = 0; i < possibleParents.length; i++) {
            if (possibleParents[i].id === possibleChild.parent_id) {
                found = true;
                // delete possibleChild.parent_id;
                if (possibleParents[i].children) {
                    possibleParents[i].children.push(possibleChild);
                } else {
                    possibleParents[i].children = [possibleChild];
                }
                possibleParents[i].count = possibleParents[i].children.length;
                return true;
            } else if (possibleParents[i].children) {
                found = this.findParent(possibleParents[i].children, possibleChild);
            }
        }
        return found;
    }

    addNewItem(node: any) {
        this.createItemNode = true;
        const currentNode = this.flatNodeMap.get(node);
        if (currentNode.children === undefined) {
            currentNode['children'] = [];
        }
        currentNode.children.push({ label: '', parent_id: currentNode.id, public: currentNode.public } as ItemNode);
        this.dataChange.next(this.data);

        this.treeControl.expand(node);
        this.renderer.selectRootElement('#itemValue').focus();
    }

    saveNode(node: any, value: any) {
        this.http.post('../rest/folders', { label: value, parent_id: node.parent_id }).pipe(
            tap((data: any) => {
                const nestedNode = this.flatNodeMap.get(node);
                nestedNode.label = value;
                nestedNode.id = data.folder;
                nestedNode.countResources = 0;
                this.dataChange.next(this.data);
                this.treeControl.collapseAll();
                this.openTree(nestedNode.id);
                this.createItemNode = false;
                this.foldersService.getPinnedFolders();
            }),
            tap(() => this.notify.success(this.translate.instant('lang.folderAdded'))),
            catchError((err) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    removeTemporaryNode(node: any) {
        const parentNode = this.getParentNode(node);
        const index = parentNode.children.map(nodeItem => nodeItem.id).indexOf(node.id);

        if (index !== -1) {
            parentNode.children.splice(index, 1);
        }

        this.flatNodeMap.delete(node);
        this.dataChange.next(this.data);
        this.createItemNode = false;
    }

    openCreateFolderModal() {
        this.dialogRef = this.dialog.open(FolderCreateModalComponent, { panelClass: 'maarch-modal', data: { folderName: this.autocomplete.getValue() } });
        this.dialogRef.afterClosed().pipe(
            filter((folderId: number) => !this.functions.empty(folderId)),
            tap(() => {
                this.autocomplete.resetValue();
                this.getFolders();
                this.foldersService.getPinnedFolders();
            }),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    deleteNode(node: any) {

        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/folders/' + node.id)),
            tap(() => {
                const parentNode = this.getParentNode(node);

                if (parentNode !== null) {
                    const index = parentNode.children.map(nodeItem => nodeItem.id).indexOf(node.id);

                    if (index !== -1) {
                        parentNode.children.splice(index, 1);
                    }
                } else {
                    const index = this.data.map(nodeItem => nodeItem.id).indexOf(node.id);
                    if (index !== -1) {
                        this.data.splice(index, 1);
                    }
                }
                this.flatNodeMap.delete(node);
                this.dataChange.next(this.data);

            }),
            tap(() => {
                this.foldersService.getPinnedFolders();
                this.notify.success(this.translate.instant('lang.folderDeleted'));
            }),
            catchError((err) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    drop(ev: any, node: any) {
        this.foldersService.classifyDocument(ev, node);
    }

    dragEnter(node: any) {
        node.drag = true;
    }

    openFolderAdmin(node: any) {
        this.dialogRef = this.dialog.open(FolderUpdateComponent, { panelClass: 'maarch-modal', autoFocus: false, data: { folderId: node.id } });

        this.dialogRef.afterClosed().pipe(
            tap((data) => {
                if (data !== undefined) {
                    this.getFolders();
                    this.foldersService.getPinnedFolders();
                    this.foldersService.setEvent({ type: 'refreshFolderInformations', content: node });
                }
            })
        ).subscribe();
    }

    checkRights(node: any) {
        let userEntities: any[] = [];
        let currentUserId: number = 0;

        this.http.get(`../rest/folders/${node.id}`).pipe(
            tap((data: any) => {
                userEntities = this.headerService.user.entities.map((info: any) => info.id);
                currentUserId = this.headerService.user.id;

                let canAdmin = false;
                let canDelete = false;
                let canAdd = true;

                const compare = data.folder.sharing.entities.filter((item: any) => userEntities.indexOf(item) > -1);

                const entitiesCompare = data.folder.sharing.entities.filter((item: any) => compare.indexOf(item.id));

                entitiesCompare.forEach((element: any) => {
                    if (element.edition === true) {
                        canAdmin = true;
                    }
                    if (element.canDelete === true) {
                        canDelete = true;
                    }
                });
                if (data.folder.user_id !== currentUserId && node.public) {
                    canAdd = false;
                }

                node.edition = (canAdmin || data.folder.user_id === currentUserId) ? true : false;
                node.canAdd = node.edition;
                node.canDelete = canDelete || data.folder.user_id === currentUserId;
            }),
        ).subscribe();
    }

    goTo(folder: any) {
        this.selectedId = folder.id;
        this.getFolders();
        this.router.navigate(['/folders/' + folder.id]);
    }

    togglePinFolder(folder: any) {
        if (folder.pinned) {
            this.foldersService.unpinFolder(folder);
        } else {
            this.foldersService.pinFolder(folder);
        }
    }

    ngOnDestroy() {
        // unsubscribe to ensure no memory leaks
        this.subscription.unsubscribe();
    }

    private getParentNode(node: any) {
        const currentLevel = node.level;
        if (currentLevel < 1) {
            return null;
        }
        const startIndex = this.treeControl.dataNodes.indexOf(node) - 1;
        for (let i = startIndex; i >= 0; i--) {
            const currentNode = this.treeControl.dataNodes[i];
            if (currentNode.level < currentLevel) {
                return this.flatNodeMap.get(currentNode);
            }
        }
        return null;
    }
}
