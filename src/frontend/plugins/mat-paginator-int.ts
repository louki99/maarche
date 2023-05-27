
import { Injectable } from '@angular/core';
import { MatPaginatorIntl } from '@angular/material/paginator';
import { TranslateService } from '@ngx-translate/core';

@Injectable()
export class CustomMatPaginatorIntl extends MatPaginatorIntl {
    constructor(
        public translate: TranslateService,
    ) {
        super();

        this.getAndInitTranslations();
    }

    getAndInitTranslations() {

        this.itemsPerPageLabel = '';
        this.nextPageLabel = this.translate.instant('lang.nextPage');
        this.previousPageLabel = this.translate.instant('lang.prevPage');
        this.changes.next();

    }

    getRangeLabel = (page: number, pageSize: number, length: number) => {
        if (length === 0 || pageSize === 0) {
            return `0 / ${length}`;
        }
        length = Math.max(length, 0);

        const nbPage = Math.ceil(length / pageSize);

        return `${this.translate.instant('lang.page')} ${page + 1} / ${nbPage}`;
    };
}
