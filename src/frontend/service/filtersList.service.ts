import { Injectable } from '@angular/core';

interface ListProperties {
    'id': number;
    'groupId': number;
    'targetId': number;
    'page': string;
    'pageSize': number;
    'order': string;
    'orderDir': string;
    'search': string;
    'delayed': boolean;
    'categories': string[];
    'priorities': string[];
    'entities': string[];
    'subEntities': string[];
    'statuses': string[];
    'doctypes': string[];
    'folders': string[];
}

@Injectable()
export class FiltersListService {

    listsProperties: any[] = [];
    listsPropertiesIndex: number = 0;
    filterMode: boolean = false;
    mode: string = 'basket';

    constructor() { }

    initListsProperties(userId: number, groupId: number, targetId: number, mode: string, specificChrono: string = '') {

        this.listsProperties = JSON.parse(sessionStorage.getItem('propertyList' + mode));

        this.listsPropertiesIndex = 0;
        this.mode = mode;
        let listProperties: ListProperties;

        if (this.listsProperties != null) {
            this.listsProperties.forEach((element, index) => {
                if (element.id == userId && element.groupId == groupId && element.targetId == targetId) {
                    this.listsPropertiesIndex = index;
                    listProperties = element;
                }
            });
        } else {
            this.listsProperties = [];
        }

        if (!listProperties || specificChrono !== '') {
            listProperties = {
                'id': userId,
                'groupId': groupId,
                'targetId': targetId,
                'page': '0',
                'pageSize': 10,
                'order': '',
                'orderDir': 'DESC',
                'search': specificChrono,
                'delayed': false,
                'categories': [],
                'priorities': [],
                'entities': [],
                'subEntities': [],
                'statuses': [],
                'doctypes': [],
                'folders': [],
            };
            this.listsProperties.push(listProperties);
            this.listsPropertiesIndex = this.listsProperties.length - 1;
            this.saveListsProperties();
        }
        return listProperties;
    }

    updateListsPropertiesPage(page: number) {
        if (this.listsProperties) {
            this.listsProperties[this.listsPropertiesIndex].page = page;
            this.saveListsProperties();
        }
    }

    updateListsPropertiesPageSize(pageSize: number) {
        if (this.listsProperties) {
            this.listsProperties[this.listsPropertiesIndex].pageSize = pageSize;
            this.saveListsProperties();
        }
    }

    updateListsProperties(listProperties: any) {
        if (this.listsProperties) {
            this.listsProperties[this.listsPropertiesIndex] = listProperties;
            this.saveListsProperties();
        }
    }

    saveListsProperties() {
        sessionStorage.setItem('propertyList' + this.mode, JSON.stringify(this.listsProperties));
    }

    getUrlFilters() {
        let filters = '';
        if (this.listsProperties) {
            if (this.listsProperties[this.listsPropertiesIndex].delayed) {
                filters += '&delayed=true';
            }
            if (this.listsProperties[this.listsPropertiesIndex].order.length > 0) {
                filters += '&order=' + this.listsProperties[this.listsPropertiesIndex].order + ' ' + this.listsProperties[this.listsPropertiesIndex].orderDir;
            }
            if (this.listsProperties[this.listsPropertiesIndex].search.length > 0) {
                filters += '&search=' + this.listsProperties[this.listsPropertiesIndex].search;
            }
            if (this.listsProperties[this.listsPropertiesIndex].categories.length > 0) {
                const cat: any[] = [];
                this.listsProperties[this.listsPropertiesIndex].categories.forEach((element: any) => {
                    cat.push(element.id);
                });

                filters += '&categories=' + cat.join(',');
            }
            if (this.listsProperties[this.listsPropertiesIndex].priorities.length > 0) {
                const prio: any[] = [];
                this.listsProperties[this.listsPropertiesIndex].priorities.forEach((element: any) => {
                    prio.push(element.id);
                });

                filters += '&priorities=' + prio.join(',');
            }
            if (this.listsProperties[this.listsPropertiesIndex].statuses.length > 0) {
                const status: any[] = [];
                this.listsProperties[this.listsPropertiesIndex].statuses.forEach((element: any) => {
                    status.push(element.id);
                });

                filters += '&statuses=' + status.join(',');
            }

            if (this.listsProperties[this.listsPropertiesIndex].entities.length > 0) {
                const ent: any[] = [];
                this.listsProperties[this.listsPropertiesIndex].entities.forEach((element: any) => {
                    ent.push(element.id);
                });

                filters += '&entities=' + ent.join(',');
            }
            if (this.listsProperties[this.listsPropertiesIndex].subEntities.length > 0) {
                const ent: any[] = [];
                this.listsProperties[this.listsPropertiesIndex].subEntities.forEach((element: any) => {
                    ent.push(element.id);
                });

                filters += '&entitiesChildren=' + ent.join(',');
            }
            if (this.listsProperties[this.listsPropertiesIndex].doctypes.length > 0) {
                const doct: any[] = [];
                this.listsProperties[this.listsPropertiesIndex].doctypes.forEach((element: any) => {
                    doct.push(element.id);
                });

                filters += '&doctypes=' + doct.join(',');
            }
            if (this.listsProperties[this.listsPropertiesIndex].folders.length > 0) {
                const folders: any[] = [];
                this.listsProperties[this.listsPropertiesIndex].folders.forEach((element: any) => {
                    folders.push(element.id);
                });

                filters += '&folders=' + folders.join(',');
            }
        }
        return filters;
    }

}
