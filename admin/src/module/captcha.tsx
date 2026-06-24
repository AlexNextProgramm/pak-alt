import { integ } from "@rocet/integration";
import { $, Rocet } from "@rocet/rocet";
import '../css/module/captcha.scss';
import { th } from "@rocet/RocetNodeElements";
export class Captcha { 

    public currentNumber:Number;
    public canvas = '#captchaCanvas';
    public isConfirm = false;
    public callback: Function;
    static userClicked = false;
    static userMovedMouse = false;

    constructor(callback: Function, Element: Rocet) {
        this.callback = callback;
        if (Element.find(this.canvas).length != 0) { 
            return;
        }
        if (window['normalCaptcha']) {
            this.notArobot(callback, Element);
            return;
        }
        let obj = this;
        const $captcha: Rocet = $(<div />).render(($rocet: Rocet) => {
            return (
                <div className="captha_block">
                    <canvas id="captchaCanvas" width="200" height="70"></canvas>
                    <div className="block_buttons">
                        <input className="captha" type="text" name="captcha" onkeyup={(ev:MouseEvent)=>obj.check(ev, obj)} maxlength="4"></input>
                        <span className="refresh" onclick={()=>obj.generateCaptcha()}></span>
                    </div>
                </div>
            )
        });

        Element.add($captcha.item(0));
        this.generateCaptcha();
    }
    notArobot(callback: Function, Element: Rocet) {
        if ($(this.canvas).length > 0) { 
            return;
        }
         const $captcha: Rocet = $(<div />).render(($rocet: Rocet) => {
            return (
                <div className="captha_block">
                    <div className="flex-row-center border">
                        <span className="block_notArobot" style={"width: 30px; height: 30px;"}>
                            <canvas id="canvasnotArobot" width="30" height="30"></canvas>
                        </span>
                        <p>Я не робот</p>
                    </div>
                </div>
            )
         });

        if (Element.find('#canvasnotArobot').length == 0) { 
            Element.add($captcha.item(0));
        }
        const canvas = document.querySelector('#canvasnotArobot') as HTMLCanvasElement;
        const ctx = canvas.getContext('2d');
        if (ctx) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            let outerSquare = { x: Math.floor(Math.random() * 10), y: Math.floor(Math.random() * 10), size: 12 }; // изменено с 30 на 20
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 1;
            ctx.strokeRect(outerSquare.x, outerSquare.y, outerSquare.size, outerSquare.size);

            canvas.addEventListener('click', function (e) { 
                const rect = canvas.getBoundingClientRect();
                const clickX = e.clientX - rect.left;
                const clickY = e.clientY - rect.top;
                if (
                    clickX >= outerSquare.x &&
                    clickX <= outerSquare.x + outerSquare.size &&
                    clickY >= outerSquare.y &&
                    clickY <= outerSquare.y + outerSquare.size
                ) {
                    callback();
                    let size = 20;
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    const checkStartX = 5 + size * 0.2; // чуть левее внутри квадрата
                    const checkStartY = 2 + size * 0.55; // чуть ниже центра
                    const checkMidX = 5 + size * 0.45;   // середина галочки по горизонтали
                    const checkMidY = 2+ size * 0.75;   // чуть ниже середины
                    const checkEndX = 5 + size * 0.8;    // правее внутри квадрата
                    const checkEndY =2  + size * 0.3;    // чуть выше центра

                    ctx.beginPath();
                    ctx.moveTo(checkStartX, checkStartY);
                    ctx.lineTo(checkMidX, checkMidY);
                    ctx.lineTo(checkEndX, checkEndY);
                    ctx.stroke();
                }
            })
    }
    }


    check(ev: MouseEvent, obj:Captcha) {
        let input = ev.target as HTMLInputElement
        let str = input.value
        if (str.length == 4) {
            if (Number(str) == obj.currentNumber) {
                obj.callback()
            } else {
                input.setAttribute('data-error', '1');
                this.generateCaptcha();
            }
        } else { 
            input.removeAttribute('data-error')

        }
    }

    generateCaptcha() {
        if (document.querySelectorAll('#canvasnotArobot').length > 0) { 
            document.querySelector('#canvasnotArobot').remove();
        }
        this.currentNumber = Math.floor(Math.random() * 9000) + 1000; // от 1000 до 9999
        const canvas = document.querySelector(this.canvas) as HTMLCanvasElement;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        // Фон
        ctx.fillStyle = '#f0f0f0';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        // Настройки текста
        const fontSize = Math.floor(Math.random() * 15) + 30; // от 30 до 45
        ctx.font = `${fontSize}px Arial`;
        // Случайное положение текста
        const x = Math.random() * (canvas.width - 100);
        const y = Math.random() * (canvas.height - fontSize) + fontSize;
    
        // Цвет текста
        ctx.fillStyle = this.getRandomColor();
        
        // Поворот текста для усложнения
        const angle = (Math.random() - 0.5) * Math.PI /4; // -45° до +45°
        
        ctx.save();
        ctx.translate(x + fontSize/2, y - fontSize/2);
        ctx.rotate(angle);
        
        ctx.fillText(this.currentNumber.toString(), -fontSize/2, fontSize/2);
        
        ctx.restore();
        
        // Добавляем шум
        this.addNoise(ctx, canvas.width, canvas.height);
    }
    
    addNoise(ctx:any, width:any, height:any) {
        for (let i = 0; i < 100; i++) {
            ctx.fillStyle = this.getRandomColor();
            ctx.beginPath();
            ctx.arc(Math.random() * width, Math.random() * height, Math.random() * 3 + 1, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    getRandomColor() {
       const r = Math.floor(Math.random() *256);
       const g = Math.floor(Math.random() *256);
       const b = Math.floor(Math.random() *256);
       return `rgb(${r},${g},${b})`;
    }

    static behavior() {
        let startTime:any = null;
        let timeClick: any = null;
        
        window.onload = () => {
            startTime = Date.now();
        };
        document.addEventListener('click', () => {
            if (!timeClick) { 
                timeClick = Date.now() 
            }
            if (!Captcha.userClicked && (Date.now() - timeClick) / 1000 > 1) {
                Captcha.userClicked = true;
            }
           
        });
        document.addEventListener('mousemove', () => {
            Captcha.userMovedMouse = true;
        });

        const checkConditionsInterval = setInterval(() => {
            const elapsedSeconds = (Date.now() - startTime) / 1000;
            if (elapsedSeconds >= 5  && Captcha.userClicked  && Captcha.userMovedMouse) {
                window['normalCaptcha'] = true
            } else {
                window['normalCaptcha'] = false;
            }
        }, 500);
    }
}

