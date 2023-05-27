import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';

interface Tiles {
    'myLastResources': Tile;
    'basket': Tile;
    'searchTemplate': Tile;
    'followedMail': Tile;
    'folder': Tile;
    'externalSignatoryBook': Tile;
    'shortcut': Tile;
}

interface Tile {
    'icon': string; // icon of tile
    'menus': ('delete' | 'view' | 'color')[]; // action of tile
    'views': TileView[]; // views tile
}

interface TileView {
    'id': 'list' | 'summary' | 'chart'; // identifier
    'route': string; // router when click on tile
    'viewDocRoute'?: string; // router when view a doc (usefull for list view)
    'target'?: string; // route target after click
}

@Injectable()
export class DashboardService {

    tileTypes: Tiles = {
        myLastResources: {
            icon: 'fa fa-history',
            menus: [
                'view',
                'color',
                'delete'
            ],
            views: [
                {
                    id: 'list',
                    route: '/resources/:resId',
                    viewDocRoute: '/resources/:resId/thumbnail'
                },
                {
                    id: 'summary',
                    route: null
                },
                {
                    id: 'chart',
                    route: null
                }
            ]
        },
        basket: {
            icon: 'fa fa-inbox',
            menus: [
                'view',
                'color',
                'delete'
            ],
            views: [
                {
                    id: 'list',
                    route: ':basketRoute',
                    viewDocRoute: '/resources/:resId/thumbnail'
                },
                {
                    id: 'summary',
                    route: '/basketList/users/:userId/groups/:groupId/baskets/:basketId'
                },
                {
                    id: 'chart',
                    route: '/basketList/users/:userId/groups/:groupId/baskets/:basketId'
                }
            ]
        },
        searchTemplate: {
            icon: 'fa fa-search',
            menus: [
                'view',
                'color',
                'delete'
            ],
            views: [
                {
                    id: 'list',
                    route: '/resources/:resId',
                    viewDocRoute: '/resources/:resId/thumbnail'
                },
                {
                    id: 'summary',
                    route: '/search?searchTemplateId=:searchTemplateId'
                },
                {
                    id: 'chart',
                    route: '/search?searchTemplateId=:searchTemplateId'
                }
            ]
        },
        followedMail: {
            icon: 'fa fa-star',
            menus: [
                'view',
                'color',
                'delete'
            ],
            views: [
                {
                    id: 'list',
                    route: '/resources/:resId',
                    viewDocRoute: '/resources/:resId/thumbnail'
                },
                {
                    id: 'summary',
                    route: '/followed'
                },
                {
                    id: 'chart',
                    route: '/followed'
                }
            ]
        },
        folder: {
            icon: 'fa fa-folder',
            menus: [
                'view',
                'color',
                'delete'
            ],
            views: [
                {
                    id: 'list',
                    route: '/resources/:resId',
                    viewDocRoute: '/resources/:resId/thumbnail'
                },
                {
                    id: 'summary',
                    route: '/folders/:folderId'
                },
                {
                    id: 'chart',
                    route: '/folders/:folderId'
                }
            ]
        },
        externalSignatoryBook: {
            icon: 'fas fa-pen-nib',
            menus: [
                'view',
                'color',
                'delete'
            ],
            views: [
                // Maarch Parapheur views
                {
                    id: 'list',
                    route: ':externalSignatoryBookUrl/dist/#/documents/:resId',
                    target: 'maarchParapheur',
                    viewDocRoute: null
                },
                {
                    id: 'summary',
                    route: ':externalSignatoryBookUrl/dist/#/home',
                    target: 'maarchParapheur',
                },
                /* Fast Parapheur views
                {
                    id: 'list',
                    route: ':externalSignatoryBookUrl/parapheur/showDoc.action?documentid=:resId',
                    target: 'fastParapheur',
                    viewDocRoute: null
                },
                {
                    id: 'summary',
                    route: ':externalSignatoryBookUrl/parapheur/showDoc.action',
                    target: 'fastParapheur',
                } */
            ]
        },
        shortcut: {
            icon: null,
            menus: [
                'color',
                'delete'
            ],
            views: [
                {
                    id: 'summary',
                    route: ':privRoute'
                }
            ]
        },
    };

    charts: any[] =  [
        {
            icon: 'fas fa-chart-pie',
            type: 'pie',
            modes: [
                'doctype',
                'status',
                'destination'
            ],
        },
        {
            icon: 'far fa-chart-bar',
            type: 'vertical-bar',
            modes: [
                'doctype',
                'status',
                'destination'
            ],
        },
        {
            icon: 'fas fa-chart-line',
            type: 'line',
            modes: [
                'creationDate',
            ],
        }
    ];
    constructor(
        public http: HttpClient,
        public translate: TranslateService,
        private notify: NotificationService
    ) { }

    getTile(id: string) {
        return this.tileTypes[id];
    }

    getTileTypes() {
        return Object.keys(this.tileTypes);
    }

    getViewsByTileType(tileType: string) {
        return this.tileTypes[tileType].views;
    }

    getCharts() {
        return this.charts.map((item: any) => ({
            ...item,
            modes: item.modes.map((chartMode: any) => ({
                id: chartMode,
                label: this.translate.instant('lang.' + chartMode)
            }))
        }));
    }

    getChartTypes() {
        return this.charts.map((chartType: any) => ({
            icon : chartType.icon,
            type: chartType.type
        }));
    }

    getChartModes(charType: string) {
        return this.charts.filter((chart: any) => chart.type === charType)[0].modes.map((chartMode: any) => ({
            id: chartMode,
            label: this.translate.instant('lang.' + chartMode)
        }));
    }

    getColors() {
        return [
            '#ef9a9a',
            '#f48fb1',
            '#ce93d8',
            '#b39ddb',
            '#9fa8da',
            '#90caf9',
            '#81d4fa',
            '#80deea',
            '#80cbc4',
            '#a5d6a7',
            '#c5e1a5',
            '#e6ee9c',
            '#fff59d',
            '#ffe082',
            '#ffcc80',
            '#ffab91',
            '#bcaaa4',
            '#b0bec5',
        ];
    }

    getFormatedRoute(route: string, data: any) {
        const regex = /:\w*/g;
        let  res = route.match(regex);

        let formatedRoute = route;
        let errors = [];

        if (res !== null) {
            let routeIdValue = null;
            errors = res.slice();

            res.forEach((routeId: any) => {
                routeIdValue = data[routeId.replace(':', '')];
                if (routeIdValue !== undefined) {
                    formatedRoute = formatedRoute.replace(routeId, routeIdValue);
                    errors.splice(errors.indexOf(routeId), 1);
                }
            });
        }
        if (errors.length === 0) {
            const objParams = {};
            const splitFormatedRoute = formatedRoute.split('?');
            if (splitFormatedRoute.length === 2) {
                const arrUriParams = splitFormatedRoute[1].split('=');
                for (let index = 0; index < arrUriParams.length; index = index + 2) {
                    objParams[arrUriParams[index]] = arrUriParams[index + 1];
                }
            }

            res = splitFormatedRoute[0].match(regex);
            if (res !== null && res[0] !== ':') {
                return this.getFormatedRoute(splitFormatedRoute[0], data);
            }  else {
                return {
                    route: splitFormatedRoute[0],
                    params: objParams
                };
            }
        } else {
            this.notify.error(errors + ' not found');
            return false;
        }
    }
}
