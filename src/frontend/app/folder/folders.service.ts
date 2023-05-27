import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { Subject, Observable, of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog } from '@angular/material/dialog';
import { Router } from '@angular/router';
import { map, tap, filter, exhaustMap, catchError, finalize } from 'rxjs/operators';


@Injectable()
export class FoldersService {

    loading: boolean = true;

    pinnedFolders: any = [];

    folders: any = [];

    currentFolder: any = { id: 0 };

    private eventAction = new Subject<any>();

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialog: MatDialog,
        private notify: NotificationService,
        private router: Router
    ) {
    }

    ngOnInit(): void { }

    initFolder() {
        this.currentFolder = { id: 0 };
    }

    catchEvent(): Observable<any> {
        return this.eventAction.asObservable();
    }

    setEvent(content: any) {
        return this.eventAction.next(content);
    }

    goToFolder(folder: any) {
        this.setFolder(folder);
        this.router.navigate(['/folders/' + folder.id]);
    }

    setFolder(folder: any) {
        this.currentFolder = folder;
        this.eventAction.next(folder);
    }

    getCurrentFolder() {
        return this.currentFolder;
    }

    getFolders() {
        this.http.get('../rest/folders').pipe(
            tap((data: any) => {
                this.folders = data.folders;
                this.eventAction.next({type: 'initTree', content: ''});
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getList() {
        return this.folders;
    }

    getPinnedFolders() {
        this.loading = true;

        this.http.get('../rest/pinnedFolders').pipe(
            tap((data: any) => {
                this.pinnedFolders = data.folders;
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    setFolders(folders: any) {
        this.folders = folders;
    }

    getPinnedList() {
        return this.pinnedFolders;
    }

    pinFolder(folder: any) {
        this.http.post(`../rest/folders/${folder.id}/pin`, {}).pipe(
            tap(() => {
                this.getPinnedFolders();
                this.eventAction.next({type: 'refreshFolderPinned', content: {id: folder.id, pinned : true}});
                this.notify.success(this.translate.instant('lang.folderPinned'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    unpinFolder(folder: any) {
        this.http.delete(`../rest/folders/${folder.id}/unpin`).pipe(
            tap(() => {
                this.getPinnedFolders();
                this.eventAction.next({type: 'refreshFolderPinned', content: {id: folder.id, pinned : false}});
                this.notify.success(this.translate.instant('lang.folderUnpinned'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getDragIds() {
        const treeList = this.folders.map((folder: any) => 'treefolder-list-' + folder.id);
        const list = this.pinnedFolders.map((folder: any) => 'folder-list-' + folder.id);

        return list.concat(treeList);
    }

    classifyDocument(ev: any, folder: any) {
        this.http.post(`../rest/folders/${folder.id}/resources`, { resources: [ev.item.data.resId] }).pipe(
            tap((data: any) => {
                if (this.pinnedFolders.filter((pinFolder: any) => pinFolder.id === folder.id)[0] !== undefined) {
                    this.pinnedFolders.filter((pinFolder: any) => pinFolder.id === folder.id)[0].countResources = data.countResources;
                }
                this.eventAction.next({type: 'refreshFolderCount', content: {id: folder.id, countResources : data.countResources}});
            }),
            tap(() => {
                this.notify.success(this.translate.instant('lang.mailClassified'));
                this.eventAction.next({type: 'function', content: 'refreshDao'});
            }),
            finalize(() => folder.drag = false),
            catchError((err) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getLoader() {
        return this.loading;
    }
}
