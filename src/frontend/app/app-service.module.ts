import { NgModule } from '@angular/core';

import { TimeAgoPipe } from '@plugins/timeAgo.pipe';
import { TimeLimitPipe } from '@plugins/timeLimit.pipe';
import { FilterListPipe } from '@plugins/filterList.pipe';
import { FullDatePipe } from '@plugins/fullDate.pipe';
import { SafeHtmlPipe } from '@plugins/safeHtml.pipe';
import { SecureUrlPipe } from '@plugins/secureUrl.pipe';
import { NgStringPipesModule } from 'ngx-pipes';
import { LatinisePipe } from 'ngx-pipes';
import { SortPipe } from '@plugins/sorting.pipe';
import { HighlightPipe } from '@plugins/highlight.pipe';
import { SplitLoginPwdPipe } from '@plugins/splitLoginPwd.pipe';

@NgModule({
    imports: [
        NgStringPipesModule
    ],
    declarations: [
        FilterListPipe,
        FullDatePipe,
        SafeHtmlPipe,
        SecureUrlPipe,
        SortPipe,
        TimeAgoPipe,
        TimeLimitPipe,
        HighlightPipe,
        SplitLoginPwdPipe
    ],
    exports: [
        NgStringPipesModule,
        FilterListPipe,
        FullDatePipe,
        SafeHtmlPipe,
        SecureUrlPipe,
        SortPipe,
        TimeAgoPipe,
        TimeLimitPipe,
        HighlightPipe,
        SplitLoginPwdPipe
    ],
    providers: [
        LatinisePipe
    ]
})
export class AppServiceModule {}
