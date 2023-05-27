import { platformBrowserDynamic } from '@angular/platform-browser-dynamic';
import { enableProdMode } from '@angular/core';

// import 'hammerjs';

import { AppModule } from './app/app.module';
import { environment } from './environments/environment';

if (environment.production) {
    window.console.debug = function() {};
    enableProdMode();
}

platformBrowserDynamic().bootstrapModule(AppModule);
