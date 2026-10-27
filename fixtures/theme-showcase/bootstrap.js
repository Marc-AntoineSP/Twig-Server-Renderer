import {Application} from '@hotwired/stimulus';
import Showcase from './controller.js';
import Tabs from '../showcase/tabs/controller.js';
import Dialog from '../showcase/dialog/controller.js';

const application = Application.start();
application.register('showcase', Showcase);
application.register('showcase-tabs', Tabs);
application.register('showcase-dialog', Dialog);
