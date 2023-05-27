import { Injectable, ComponentFactoryResolver, Injector, ApplicationRef, ViewContainerRef, TemplateRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { tap, catchError, map } from 'rxjs/operators';
import { of } from 'rxjs';
import { MatSidenav } from '@angular/material/sidenav';
import { FoldersService } from '../app/folder/folders.service';
import { DomPortalOutlet, TemplatePortal } from '@angular/cdk/portal';

@Injectable({
    providedIn: 'root'
})
export class HeaderService {
    sideBarForm: boolean = false;
    sideBarAdmin: boolean = false;
    hideSideBar: boolean = false;
    showhHeaderPanel: boolean = true;
    showMenuShortcut: boolean = true;
    showMenuNav: boolean = true;

    sideNavLeft: MatSidenav = null;
    sideBarButton: any = null;

    currentBasketInfo: any = {
        ownerId: 0,
        groupId: 0,
        basketId: ''
    };
    folderId: number = 0;
    headerMessageIcon: string = '';
    headerMessage: string = '';
    subHeaderMessage: string = '';
    user: any = { firstname: '', lastname: '', groups: [], privileges: [], preferences: [], featureTour: [] };
    nbResourcesFollowed: number = 0;
    base64: string = null;

    private portalHost: DomPortalOutlet;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public foldersService: FoldersService,
        private componentFactoryResolver: ComponentFactoryResolver,
        private injector: Injector,
        private appRef: ApplicationRef,
    ) { }

    loadHeader() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/header').pipe(
                tap((data: any) => {
                    this.setUser(data.user);
                    resolve(true);
                }),
                catchError((err: any) => {
                    console.log(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });

    }

    resfreshCurrentUser() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/currentUser/profile')
                .pipe(
                    map((data: any) => {
                        this.user = {
                            mode: data.mode,
                            id: data.id,
                            userId: data.user_id,
                            mail: data.mail,
                            firstname: data.firstname,
                            lastname: data.lastname,
                            entities: data.entities,
                            groups: data.groups,
                            preferences: data.preferences,
                            privileges: data.privileges[0] === 'ALL_PRIVILEGES' ? this.user.privileges : data.privileges,
                            featureTour: data.featureTour
                        };
                        this.nbResourcesFollowed = data.nbFollowedResources;
                        resolve(true);
                    })
                ).subscribe();
        });

    }

    setUser(user: any = { firstname: '', lastname: '', groups: [], privileges: [] }) {
        this.user = user;
    }

    getLastLoadedFile() {
        return this.base64;
    }

    setLoadedFile(base64: string) {
        this.base64 = base64;
    }

    setHeader(maintTitle: string, subTitle: any = '', icon = '') {
        this.headerMessage = maintTitle;
        this.subHeaderMessage = subTitle;
        this.headerMessageIcon = icon;
    }

    resetSideNavSelection() {
        this.currentBasketInfo = {
            ownerId: 0,
            groupId: 0,
            basketId: ''
        };
        this.foldersService.setFolder({ id: 0 });
        this.sideBarForm = false;
        this.showhHeaderPanel = true;
        this.showMenuShortcut = true;
        this.showMenuNav = true;
        this.sideBarAdmin = false;
        this.sideBarButton = null;
        this.hideSideBar = true;
    }

    injectInSideBarLeft(template: TemplateRef<any>, viewContainerRef: ViewContainerRef, id: string = 'adminMenu', mode: string = '') {

        if (mode === 'form') {
            this.sideBarForm = true;
            this.showhHeaderPanel = true;
            this.showMenuShortcut = false;
            this.showMenuNav = false;
            this.sideBarAdmin = true;
        } else {
            this.showhHeaderPanel = true;
            this.showMenuShortcut = true;
            this.showMenuNav = true;
        }
        // Create a portalHost from a DOM element
        this.portalHost = new DomPortalOutlet(
            document.querySelector(`#${id}`),
            this.componentFactoryResolver,
            this.appRef,
            this.injector
        );

        // Create a template portal
        const templatePortal = new TemplatePortal(
            template,
            viewContainerRef
        );

        // Attach portal to host
        this.portalHost.attach(templatePortal);
    }

    initTemplate(template: TemplateRef<any>, viewContainerRef: ViewContainerRef, id: string = 'adminMenu', mode: string = '') {
        // Create a portalHost from a DOM element
        this.portalHost = new DomPortalOutlet(
            document.querySelector(`#${id}`),
            this.componentFactoryResolver,
            this.appRef,
            this.injector
        );

        // Create a template portal
        const templatePortal = new TemplatePortal(
            template,
            viewContainerRef
        );

        // Attach portal to host
        this.portalHost.attach(templatePortal);
    }
}
