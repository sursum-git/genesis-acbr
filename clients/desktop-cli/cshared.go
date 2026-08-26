package main

/*
#include <stdlib.h>
*/
import "C"
import "unsafe"

//export AcbrApiRunConfig
func AcbrApiRunConfig(configPath *C.char) *C.char {
	if configPath == nil {
		return C.CString(`{"exit_code":1,"stdout":"","stderr":"configPath vazio"}`)
	}
	return C.CString(RunConfigForSharedLibrary(C.GoString(configPath)))
}

//export AcbrApiFree
func AcbrApiFree(ptr *C.char) {
	C.free(unsafe.Pointer(ptr))
}
