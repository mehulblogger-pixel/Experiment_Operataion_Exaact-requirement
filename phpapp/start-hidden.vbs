' ============================================================================
'  EXAACT - run in the BACKGROUND with NO black window.
'
'  Double-click this file instead of start-easy.bat. The app starts silently
'  and keeps running with no console window on screen. Your browser opens once
'  so you can sign in; after that anyone can use it while this machine is on.
'
'  To STOP the app later, double-click  stop-app.bat  (in this same folder).
'
'  (This is the simple single-machine way to lose the black window. For a whole
'   team on a network, host it properly - see the deployment guide.)
' ============================================================================
Dim sh, fso, here
Set sh  = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")
here = fso.GetParentFolderName(WScript.ScriptFullName)
sh.CurrentDirectory = here
' Window style 0 = hidden; False = do not wait. start-easy.bat locates PHP and
' runs the built-in server exactly as usual, just without a visible window.
sh.Run "cmd /c """ & here & "\start-easy.bat""", 0, False
