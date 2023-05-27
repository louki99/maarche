import { Component, OnInit, Input, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';

import { Subscription } from 'rxjs';
import { FoldersService } from '../folders.service';

@Component({
    selector: 'app-folder-pinned',
    templateUrl: 'folder-pinned.component.html',
    styleUrls: ['folder-pinned.component.scss'],
})
export class FolderPinnedComponent implements OnInit, OnDestroy {

    @Input('noInit') noInit: boolean = false;

    subscription: Subscription;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public foldersService: FoldersService
    ) {
        // Event after process action
        this.subscription = this.foldersService.catchEvent().subscribe((result: any) => {
        });
    }

    ngOnInit(): void {
        this.foldersService.initFolder();
        if (!this.noInit) {
            this.foldersService.getPinnedFolders();
        }
    }

    gotToFolder(folder: any) {
        this.foldersService.goToFolder(folder);
    }

    dragEnter(folder: any) {
        folder.drag = true;
    }

    drop(ev: any, node: any) {
        this.foldersService.classifyDocument(ev, node);
    }

    ngOnDestroy() {
        // unsubscribe to ensure no memory leaks
        this.subscription.unsubscribe();
    }

}
