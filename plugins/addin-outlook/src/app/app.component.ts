import { Component } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';


@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
})
export default class AppComponent {

  constructor(
    public translate: TranslateService,
  ) {
    translate.setDefaultLang('fr');
  }
 }
