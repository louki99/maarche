import { DatePipe } from '@angular/common';
import { Injectable } from '@angular/core';
import { FunctionsService } from './functions.service';
import { HeaderService } from './header.service';

interface ListProperties {
    'page': number;
    'pageSize': number;
    'criteria': any[];
    'filters': any;
    'order': string;
    'orderDir': string;
}

@Injectable()
export class CriteriaSearchService {

    listsProperties: ListProperties = {
        page : 0,
        pageSize : 0,
        order: 'creationDate',
        orderDir: 'DESC',
        criteria: [],
        filters: {}
    };

    constructor(
        private datePipe: DatePipe,
        public functions: FunctionsService,
        private headerService: HeaderService,
    ) { }

    initListsProperties(userId: number) {

        const crit = JSON.parse(sessionStorage.getItem('criteriaSearch_' + userId));

        if (crit !== null) {
            this.listsProperties = JSON.parse(sessionStorage.getItem('criteriaSearch_' + userId));
        } else {
            this.listsProperties = {
                page : 0,
                pageSize : 0,
                order: 'creationDate',
                orderDir: 'DESC',
                criteria: [],
                filters: {}
            };
        }

        return this.listsProperties;
    }

    updateListsPropertiesPage(page: number) {
        this.listsProperties.page = page;
        this.saveListsProperties();
    }

    updateListsPropertiesPageSize(pageSize: number) {
        this.listsProperties.pageSize = pageSize;
        this.saveListsProperties();
    }

    updateListsPropertiesCriteria(criteria: any) {
        this.listsProperties.criteria = criteria;
        this.saveListsProperties();
    }

    updateListsPropertiesFilters(filters: any) {
        this.listsProperties.filters = filters;
        this.saveListsProperties();
    }

    updateListsProperties(listProperties: ListProperties) {
        this.listsProperties = listProperties;
        this.saveListsProperties();
    }

    saveListsProperties() {
        sessionStorage.setItem('criteriaSearch_' + this.headerService.user.id, JSON.stringify(this.listsProperties));
    }

    getCriteria() {
        return this.listsProperties.criteria;
    }

    formatDatas(data: any) {
        Object.keys(data).forEach(key => {
            if (['folders', 'tags', 'registeredMail_issuingSite'].indexOf(key) > -1 || ['select', 'radio', 'checkbox'].indexOf(data[key].type) > -1) {
                data[key].values = data[key].values.map((val: any) => val.id);
            } else if (data[key].type === 'date') {
                data[key].values.start = this.datePipe.transform(data[key].values.start, 'y-MM-dd');
                data[key].values.end = this.datePipe.transform(data[key].values.end, 'y-MM-dd');
            }
            delete data[key].type;
        });

        return data;
    }
}
