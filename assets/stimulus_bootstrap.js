import { startStimulusApp } from '@symfony/stimulus-bundle';
import {Showcase, ShowcaseTabs, ShowcaseDialog} from './showcase/showcase.js';

const app = startStimulusApp();
app.register('showcase', Showcase);
app.register('showcase-tabs', ShowcaseTabs);
app.register('showcase-dialog', ShowcaseDialog);
