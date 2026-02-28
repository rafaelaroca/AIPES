# AIPES
AI Platform for Embedded Systems

AIPES is a cloud based IDE that merges Artificial Intelligence and remote compilation. It allows you to program microcontrollers directly from your web browser without installing any local software.

It was created as evolution of BIPES project (https://bipes.net.br/ide/) / (https://github.com/BIPES/BIPES) on the IA era!


1. How to ask AI for code 🤖

On the left panel, you'll find a text box. Describe what you want the board to do in natural language (e.g., "Create a traffic light using pins 2, 3, and 4").

You can also click the 🎤 Falar (Speak) button. The browser will ask for microphone access and transcribe your voice into text automatically. Click "Generate Code" and wait for the AI to write the C++ program and explain how it works.

3. Editing the Code ✍️

On the right side of the screen is a professional code editor (powered by Monaco Editor, same as VSCode). Even though the AI generates the code, you are completely free to modify variables, fix logic, or paste your own code before compiling.

5. How to Share Code 🔗


Every time code is compiled or generated, AIPES saves the project to its database and appends a unique ID to your URL (e.g., ?id=a1b2c3d4). To share your project with a friend, simply copy the full link from your browser's address bar and send it. When they open it, they will see your exact code!

7. How to Compile ⚙️

Before uploading, you must convert the text code into machine language (binary). Follow these steps:

Select your Board from the dropdown menu (ESP32-C3 or Classic ESP32).
Select the OTA / Upload Mode.

Click the blue ⚙️ Compile Code button. The server will handle the heavy lifting using arduino-cli and return a green success message.
5. Upload Methods 🚀


AIPES supports 4 amazing ways to flash your board:

USB Only (WebSerial): Connect the board via USB cable. Requires Google Chrome or Edge. The system performs a Full Flash (Bootloader, Partitions, and App) right from the browser. Best for first-time setup.

WiFi OTA (Station Mode): The AI-generated code will connect the board to your home router. Ideal for updating boards placed around your house via Wi-Fi.

WiFi OTA (Access Point Mode): The board creates its own Wi-Fi network (e.g., "AIPES_OTA"). You connect your PC to this network and flash the code directly to it, no router needed!

Bluetooth BLE OTA: Uses WebBluetooth technology. It is slower, but perfect for updating devices in the field where Wi-Fi is completely unavailable.

⚠️ Important tip for WiFi Mode: Because your board doesn't have an HTTPS certificate, Chrome might block the upload for security reasons (Mixed Content). Click the "Padlock" icon next to the URL in your browser, go to "Site Settings", and change the Insecure content permission to Allow.




