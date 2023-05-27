import { Component, OnInit, AfterViewInit, QueryList, ViewChildren } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { DashboardService } from './dashboard.service';
import { FunctionsService } from '@service/functions.service';
import { TileCreateComponent } from './tile/tile-create.component';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { MatDialog } from '@angular/material/dialog';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { PrivilegeService } from '@service/privileges.service';
import { HeaderService } from '@service/header.service';
import { ColorEvent } from 'ngx-color';
import { ExternalSignatoryBookManagerService } from '@service/externalSignatoryBook/external-signatory-book-manager.service';

@Component({
    selector: 'app-dashboard',
    templateUrl: 'dashboard.component.html',
    styleUrls: ['dashboard.component.scss'],
    providers: [DashboardService, ExternalSignatoryBookManagerService]
})
export class DashboardComponent implements OnInit, AfterViewInit {

    @ViewChildren('tileComponent') tileComponent: QueryList<any>;

    tiles: any = [];
    hoveredTool: boolean = false;
    tileErrors: any[] = [];


    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dashboardService: DashboardService,
        public dialog: MatDialog,
        public externalSignatoryBook: ExternalSignatoryBookManagerService,
        private notify: NotificationService,
        private functionsService: FunctionsService,
        private privilegeService: PrivilegeService,
        private headerService: HeaderService,
    ) { }

    ngOnInit(): void {
        this.getDashboardConfig();
    }

    ngAfterViewInit(): void { }

    enterTile(tile: any, index: number) {
        this.hoveredTool = false;
        this.tiles.forEach((element: any, indexTile: number) => {
            element.editMode = indexTile === index ? true : false;
        });
    }

    leaveTile(tile: any) {
        if (!this.hoveredTool) {
            tile.editMode = false;
        }
    }

    getDashboardConfig(position: number = null) {
        this.http.get('../rest/tiles').pipe(
            tap((data: any) => {
                for (let index = 0; index < 6; index++) {
                    if (position === index || position === null) {
                        const tmpTile = data.tiles.find((tile: any) => tile.position === index);
                        if (!this.functionsService.empty(tmpTile)) {
                            let objTile = { ...this.dashboardService.getTile(tmpTile.type), ...tmpTile };
                            if (tmpTile.type === 'shortcut') {
                                objTile = { ...objTile, ...this.initShortcutTile(tmpTile.parameters) };
                            }
                            objTile.charts = this.dashboardService.getCharts();
                            objTile.label = this.functionsService.empty(objTile.label) ? this.translate.instant('lang.' + objTile.type) : objTile.label;
                            objTile.parameters = this.functionsService.empty(objTile.parameters) ? {} : objTile.parameters;
                            if (position !== null) {
                                this.tiles[index] = objTile;
                            } else {
                                this.tiles.push(objTile);
                            }
                        } else {
                            if (position !== null) {
                                this.tiles[index] = {
                                    id: null,
                                    position: index,
                                    editMode: false
                                };
                            } else {
                                this.tiles.push({
                                    id: null,
                                    position: index,
                                    editMode: false
                                });
                            }
                        }
                    }
                }
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    initShortcutTile(param: any) {
        let menu: any = {
            style: null
        };
        let route = '';
        let label = '';

        if (param.privilegeId === 'indexing') {
            const priv = this.privilegeService.getCurrentUserMenus([param.privilegeId])[0];
            if (priv !== undefined && priv.groups.find((groupItem: any) => groupItem.id === param.groupId) !== undefined) {
                menu = priv;
                label = this.translate.instant(menu.label);
                const group = menu.groups.find((groupItem: any) => groupItem.id === param.groupId);
                route = `/indexing/${group.id}`;
                label = `${label} (${group.label})`;
            }

        } else {
            const priv = this.privilegeService.getAdminMenu([param.privilegeId])[0];
            if (priv !== undefined) {
                menu = priv;
                route = menu.route;
                label = this.translate.instant(menu.label);
            }
        }

        return {
            icon: menu.style,
            privRoute: route,
            label: label
        };
    }

    changeView(tile: any, view: string, extraParams: any = null) {
        const tileToSend = JSON.parse(JSON.stringify(tile));
        tileToSend.view = view;
        if (extraParams !== null) {
            Object.keys(extraParams).forEach(paramKey => {
                tileToSend.parameters[paramKey] = extraParams[paramKey];
            });
        }
        this.http.put(`../rest/tiles/${tile.id}`, tileToSend).pipe(
            tap(() => {
                const indexTile = this.tiles.filter((tileItem: any) => tileItem.id !== null).map((tileItem: any) => tileItem.position).indexOf(tile.position);
                this.tiles[tile.position] = tileToSend;
                this.tileComponent.toArray()[indexTile].changeView(view, extraParams);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    changeTileColor(tile: any, $event: ColorEvent) {
        this.tiles[tile.position].color = $event.color.hex;
        this.http.put(`../rest/tiles/${tile.id}`, this.tiles[tile.position]).pipe(
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    transferDataSuccess() {
        this.tiles.forEach((tile: any, index: number) => {
            tile.position = index;
        });
        this.http.put('../rest/tilesPositions', { tiles: this.tiles.filter((tile: any) => tile.id !== null) }).pipe(
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    addTilePrompt(tile: any) {
        const dialogRef = this.dialog.open(TileCreateComponent, { panelClass: 'maarch-modal', width: '450px', autoFocus: false, disableClose: true, data: { position: tile.position } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => !this.functionsService.empty(data)),
            tap((data: any) => {
                this.getDashboardConfig(tile.position);
            })
        ).subscribe();
    }

    launchAction(action: string, tile: any) {
        this[action](tile);
    }

    delete(tile: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete(`../rest/tiles/${tile.id}`)),
            tap(() => {
                this.tiles[tile.position] = {
                    id: null,
                    position: tile.position,
                    editMode: false
                };
            })
        ).subscribe();
    }

    emptyDashboard() {
        return this.tiles.filter((item: any) => item.id !== null).length === 0;
    }

    hasError(idTile: any) {
        const getTile: any = this.tileErrors.find((tile: any) => tile.id === idTile);
        return getTile !== undefined ? getTile.error : false;
    }

    getViews(tile: any) {
        if (tile.type === 'externalSignatoryBook') {
            return (tile.views as any[]).filter((item: any) => (item.target === this.externalSignatoryBook.signatoryBookEnabled));
        } else {
            return tile.views;
        }
    }
}
