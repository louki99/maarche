import { enableProdMode } from '@angular/core';
import { platformBrowserDynamic } from '@angular/platform-browser-dynamic';
import { AppModule } from './app/app.module';
import { environment } from './environments/environment';

declare const Office: any;

Office.initialize = function(){
  if (environment.production) {
    enableProdMode();
  }
  platformBrowserDynamic().bootstrapModule(AppModule)
    .catch(err => console.error(err));
};

Office.onReady((info) => {
  if (info.host === Office.HostType.Excel) {
      // Do Excel-specific initialization (for example, make add-in task pane's
      // appearance compatible with Excel "green").
  }
  if (info.platform === Office.PlatformType.PC) {
      // Make minor layout changes in the task pane.
  }
  console.log(`Office.js is now ready in ${info.host} on ${info.platform}`);
});
