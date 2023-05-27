import { NgModule } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { CommonModule } from '@angular/common';

import { TranslateLoader, TranslateModule } from '@ngx-translate/core';

@NgModule({
    imports: [
        CommonModule,
        TranslateModule.forRoot({
            loader: {
                provide: TranslateLoader,
                useFactory: HttpLoaderFactory,
                deps: [HttpClient]
            }
        }),
    ],
    declarations: [],
    exports: [TranslateModule],
    providers: []
})
export class InternationalizationModule { }

export class TranslateBackendHttpLoader implements TranslateLoader {

    constructor(private http: HttpClient) { }

    /**
     * Gets the translations from the server
     * @param lang
     * @returns {any}
     */
    public getTranslation(lang: string): any {
        return this.http.get(`../rest/languages/` + lang);
    }
}

// For traductions
export function HttpLoaderFactory(http: HttpClient) {
    return new TranslateBackendHttpLoader(http);
}

