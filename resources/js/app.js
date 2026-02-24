import './bootstrap';

import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import * as driverjs from 'driver.js';
import 'driver.js/dist/driver.css';

window.Alpine = Alpine;
window.Swal = Swal;
window.driver = { js: driverjs };

Alpine.start();
