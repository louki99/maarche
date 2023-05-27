import { Component, OnInit, Input, ViewChild, Output, EventEmitter, OnDestroy } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { FolderTreeComponent } from '../folder-tree.component';
import { FoldersService } from '../folders.service';
import { ActionsService } from '../../actions/actions.service';
import { Subscription } from 'rxjs';

@Component({
    selector: 'app-panel-folder',
    templateUrl: 'panel-folder.component.html',
    styleUrls: ['panel-folder.component.scss'],
})
export class PanelFolderComponent implements OnInit, OnDestroy {



    @Input() selectedId: number;

    @ViewChild('folderTree', { static: false }) folderTree: FolderTreeComponent;

    @Output() refreshEvent = new EventEmitter<string>();

    subscription: Subscription;

    constructor(
        public translate: TranslateService,
        public foldersService: FoldersService,
        public actionService: ActionsService
    ) {
        this.subscription = this.actionService.catchAction().subscribe(message => {

            this.refreshFoldersTree();
        });
    }

    ngOnInit(): void {
        this.foldersService.getPinnedFolders();
    }

    ngOnDestroy() {
        // unsubscribe to ensure no memory leaks
        this.subscription.unsubscribe();
    }

    initTree() {
        this.folderTree.openTree(this.selectedId);
    }

    refreshDocList() {
        this.refreshEvent.emit();
    }

    refreshFoldersTree() {
        if (this.folderTree !== undefined) {
            this.folderTree.getFolders();
        }
    }
}
