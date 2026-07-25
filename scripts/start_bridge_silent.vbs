Set WshShell = CreateObject("WScript.Shell")
WshShell.Run "python """ & Left(WScript.ScriptFullName, InStrRev(WScript.ScriptFullName, "\")) & "adb_bridge.py""", 0, False
